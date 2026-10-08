<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Concurrency\Transactions;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Transaction\EngineMix;
use MySqlMemory\Session\Transaction\RowAccess;
use MySqlMemory\Session\Transaction\Savepoints;
use MySqlMemory\Session\Transaction\Statements;
use MySqlMemory\Storage\Heap;

/**
 * The transaction of a session: its characteristics, its undo log, and how it ends.
 *
 * Before a statement changes a row of an InnoDB table, the session locks the row exclusively and
 * keeps its earlier version in the undo log of its transaction. A statement that fails puts back
 * the rows it changed (Statements); ROLLBACK puts back those of the transaction, and ROLLBACK TO
 * SAVEPOINT those changed after the savepoint, keeping the locks (Savepoints).
 * AUTO_INCREMENT values a rolled back insert took are not given back. A table of another engine,
 * such as MyISAM or MEMORY, is not transactional: its changes stay, and a rollback that cannot
 * undo them warns with ER_WARNING_NOT_COMPLETE_ROLLBACK.
 *
 * With autocommit off, a transaction lasts until COMMIT, ROLLBACK or a statement that commits
 * implicitly. A transaction takes the isolation level and access mode SET TRANSACTION gave the
 * next transaction, else those of the session. A transaction is active, as
 * SERVER_STATUS_IN_TRANS reports, from BEGIN, or once it read an InnoDB table or wrote a table.
 * What its reads see and the row locks it takes are those of RowAccess.
 *
 * While an XA transaction is active or idle, BEGIN, COMMIT, ROLLBACK and the statements that
 * commit implicitly fail with XAER_RMFAIL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html,
 * https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
 *
 * @visibility MySqlMemory
 */
final class Transaction
{
    /**
     * Whether a transaction that spans statements is open: one BEGIN started, or one autocommit off started.
     */
    public bool $open = false;

    /**
     * Whether the open transaction was started explicitly, by BEGIN, START TRANSACTION or XA START.
     */
    public bool $explicit = false;

    /**
     * Whether the transaction read an InnoDB table or wrote a table.
     */
    public bool $engaged = false;

    /**
     * The savepoints of the open transaction.
     */
    public readonly Savepoints $savepoints;

    /**
     * The statements running and the changes each restores when it fails.
     */
    public readonly Statements $statements;

    /**
     * What the reads of the transaction see, and the row locks it takes.
     */
    public readonly RowAccess $access;

    /**
     * The engines of the tables the transaction updated.
     */
    public readonly EngineMix $engineMix;

    /**
     * @var array<string, true> What the open transaction did to temporary tables that a rollback cannot undo: `created` and `dropped`
     */
    public array $temporaries = [];

    /**
     * The state of the XA transaction of the session.
     */
    public XaState $xa = XaState::NonExisting;

    /**
     * The key of the XID of the XA transaction of the session, as PreparedBranch::key answers it, or null when there is none.
     */
    public ?string $xid = null;

    /**
     * The isolation level of the transaction.
     */
    public Isolation $isolation = Isolation::RepeatableRead;

    /**
     * Whether the transaction is read-only.
     */
    public bool $readOnly = false;

    /**
     * The isolation level SET TRANSACTION gave the next transaction, or null for that of the session.
     */
    public ?Isolation $nextIsolation = null;

    /**
     * The access mode SET TRANSACTION gave the next transaction, true for READ ONLY, or null for that of the session.
     */
    public ?bool $nextReadOnly = null;

    /**
     * The snapshot of the read view of the transaction, or null before it takes one.
     */
    public ?int $snapshot = null;

    /**
     * @var list<array{StoredTable, Heap, int, list<int|float|string|null>|null}> The undo log of the transaction: for each change in order, the table, its rows, the row number, and the row before the change, null when it did not exist
     */
    public array $undo = [];

    /**
     * @var array<int, array{Heap, array<int, list<int|float|string|null>|null>}> The rows the transaction changed, by table: the rows of the table, and the version of each before the transaction changed it
     */
    public array $changed = [];

    /**
     * Whether the open transaction changed a table a rollback cannot restore.
     */
    public bool $nontransactional = false;

    /**
     * The changes the open transaction made to tables a rollback cannot restore.
     */
    public int $untracked = 0;

    /**
     * Whether the statement that failed last had changed a table its failure could not restore.
     */
    public bool $unrestored = false;

    /**
     * @param Dictionary $dictionary The databases of the server
     * @param int $id The connection id of the session
     * @param Variables|null $variables The variables of the session, which hold its isolation level, access mode, autocommit and lock wait timeout
     * @param Transactions $system The transactions of the server
     * @param Diagnostics|null $diagnostics The diagnostics area of the session, where the transaction warns
     */
    public function __construct(public readonly Dictionary $dictionary, public readonly int $id = 0, public readonly ?Variables $variables = null, public readonly Transactions $system = new Transactions(), public readonly ?Diagnostics $diagnostics = null)
    {
        $this->savepoints = new Savepoints($this);
        $this->statements = new Statements($this);
        $this->access = new RowAccess($this);
        $this->engineMix = new EngineMix($this);
    }

    /**
     * Records that the statement writes a table: the transaction becomes active.
     */
    public function touch(StoredTable $table): void
    {
        $this->engaged = true;
    }

    /**
     * Records the change a statement is about to make to a row: an update, a delete, or an insert under the next row number.
     *
     * A row of an InnoDB table is locked exclusively first, and its version before the change goes
     * to the undo log; a row of a temporary table is not locked; a change to a table of another
     * engine is not kept.
     *
     * @throws SqlError When the lock cannot be taken
     */
    public function write(StoredTable $table, int $number): void
    {
        $this->engaged = true;
        $this->engineMix->combine($table);
        if (!self::transactional($table)) {
            $this->nontransactional = $this->nontransactional || $this->open;
            $this->untracked++;
            $this->statements->unrestorable();

            return;
        }
        if (!$table->definition->temporary) {
            $this->access->lock($table, $number, LockMode::Exclusive);
        }
        $before = $table->data->rows[$number] ?? null;
        $this->undo[] = [$table, $table->data, $number, $before];
        if ($table->definition->temporary) {
            return;
        }
        $id = spl_object_id($table);
        if (($this->changed[$id][0] ?? null) !== $table->data) {
            $this->changed[$id] = [$table->data, []];
        }
        if (!array_key_exists($number, $this->changed[$id][1])) {
            $this->changed[$id][1][$number] = $before;
        }
    }

    /**
     * Tells whether a table is transactional: an InnoDB table.
     */
    public static function transactional(StoredTable $table): bool
    {
        $engine = $table->definition->engine;

        return strcasecmp($engine, 'InnoDB') === 0 || strcasecmp($engine, 'innobase') === 0;
    }

    /**
     * Opens an explicit transaction, with the characteristics START TRANSACTION names, else those SET TRANSACTION gave it, which it uses up, else those of the session.
     *
     * WITH CONSISTENT SNAPSHOT takes the snapshot at once under REPEATABLE READ.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function begin(?bool $readOnly = null, bool $snapshot = false): void
    {
        $this->guard();
        $this->isolation = $this->nextIsolation ?? $this->sessionIsolation();
        $this->readOnly = $readOnly ?? $this->nextReadOnly ?? $this->sessionReadOnly();
        $this->nextIsolation = null;
        $this->nextReadOnly = null;
        $this->undo = [];
        $this->statements->kept = [];
        $this->changed = [];
        $this->savepoints->list = [];
        $this->open = true;
        $this->explicit = true;
        $this->engaged = true;
        $this->forget();
        if ($snapshot && $this->isolation === Isolation::RepeatableRead) {
            $this->snapshot = $this->system->open($this->id);
        }
    }

    /**
     * Ends the open transaction, keeping its changes, as COMMIT and the statements that commit implicitly do; the characteristics SET TRANSACTION gave the next transaction are used up.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function commit(): void
    {
        $this->guard();
        $this->end();
        $this->nextIsolation = null;
        $this->nextReadOnly = null;
    }

    /**
     * Ends the open transaction, restoring the rows its changes replaced; the characteristics SET TRANSACTION gave the next transaction are used up.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function rollback(): void
    {
        $this->guard();
        $this->restore();
        $this->end();
        $this->nextIsolation = null;
        $this->nextReadOnly = null;
    }

    /**
     * Refuses a statement that ends or opens a transaction while an XA transaction is active or idle.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function guard(): void
    {
        if ($this->xa !== XaState::NonExisting) {
            throw StatementError::XaWrongState->error($this->xa->value);
        }
    }

    /**
     * Restores the rows the changes of the open transaction replaced.
     */
    public function restore(): void
    {
        $this->statements->undoFrom(0, false);
    }

    /**
     * Ends the open transaction, plain or XA, without restoring anything: its changes are committed, its locks released and its snapshot closed.
     *
     * The characteristics SET TRANSACTION gave the next transaction are used up when a
     * transaction that was active ends.
     */
    public function end(): void
    {
        if ($this->changed !== []) {
            $this->system->commit($this);
        }
        $this->system->locks->release($this->id);
        $this->forget();
        if ($this->engaged || $this->explicit) {
            $this->nextIsolation = null;
            $this->nextReadOnly = null;
        }
        $this->undo = [];
        $this->statements->kept = [];
        $this->changed = [];
        $this->temporaries = [];
        $this->savepoints->list = [];
        $this->open = false;
        $this->explicit = false;
        $this->engaged = false;
        $this->nontransactional = false;
        $this->untracked = 0;
        $this->engineMix->updated = [];
        $this->engineMix->combined = false;
        $this->xa = XaState::NonExisting;
        $this->xid = null;
    }

    /**
     * Ends the open transaction as XA PREPARE does: the changed rows leave the tables, which get back the rows they had when it started.
     *
     * @return list<array{StoredTable, Heap, int, list<int|float|string|null>|null}> Each row the transaction changed, with its table, the rows of the table, its number and its version after the changes, null when deleted
     */
    public function detach(): array
    {
        $changes = [];
        $seen = [];
        foreach ($this->undo as [$table, $heap, $number]) {
            $key = spl_object_id($heap) . ':' . $number;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $changes[] = [$table, $heap, $number, $heap->rows[$number] ?? null];
            }
        }
        $this->restore();
        $this->changed = [];
        $this->end();

        return $changes;
    }

    /**
     * Tells whether the transaction is active, as SERVER_STATUS_IN_TRANS reports it: begun explicitly, or open and engaged.
     */
    public function active(): bool
    {
        return $this->open && ($this->explicit || $this->engaged);
    }

    /**
     * Answers the isolation level of the session, as transaction_isolation (tx_isolation before 5.7.20) holds it.
     */
    public function sessionIsolation(): Isolation
    {
        $value = $this->variables?->read('transaction_isolation') ?? $this->variables?->read('tx_isolation');

        return Isolation::named((string) $value) ?? Isolation::RepeatableRead;
    }

    /**
     * Tells whether the session is read-only, as transaction_read_only (tx_read_only before 5.7.20) holds it.
     */
    public function sessionReadOnly(): bool
    {
        $value = $this->variables?->read('transaction_read_only') ?? $this->variables?->read('tx_read_only');

        return in_array(strtoupper((string) $value), ['ON', '1'], true);
    }

    /**
     * Tells whether autocommit is on for the session.
     */
    public function autocommit(): bool
    {
        $value = $this->variables?->read('autocommit');

        return $value === null || in_array(strtoupper((string) $value), ['ON', '1'], true);
    }

    /**
     * Closes the read view of the transaction, if it has one.
     */
    public function forget(): void
    {
        if ($this->snapshot !== null) {
            $this->snapshot = null;
            $this->system->close($this->id);
        }
    }

    /**
     * Ends the transaction of a session that disconnects: an open transaction is rolled back, whatever XA state it is in, and its locks are released.
     */
    public function disconnect(): void
    {
        $this->restore();
        $this->end();
        $this->statements->running = [];
        unset($this->system->sessions[$this->id]);
    }
}
