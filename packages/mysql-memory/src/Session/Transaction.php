<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Storage\Heap;

/**
 * The transaction of a session, and the atomicity of each statement.
 *
 * Before a statement first changes a table, the table's rows are kept; a statement that fails
 * restores them, as InnoDB rolls back a failed statement. Inside an explicit transaction the
 * rows kept at its first change are restored by ROLLBACK.
 *
 * A savepoint keeps, for each table first changed after it, the rows the table had at the
 * savepoint; ROLLBACK TO SAVEPOINT restores them and deletes the savepoints set after it, and
 * RELEASE SAVEPOINT deletes the savepoint and those set after it. Savepoint names compare without
 * regard to letter case and accents. Outside an explicit transaction SAVEPOINT sets nothing.
 *
 * While an XA transaction is active or idle, BEGIN, COMMIT, ROLLBACK and the statements that
 * commit implicitly fail with XAER_RMFAIL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html,
 * https://dev.mysql.com/doc/refman/8.4/en/savepoint.html,
 * https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
 *
 * @visibility MySqlMemory
 */
final class Transaction
{
    /**
     * Whether an explicit transaction is open.
     */
    public bool $open = false;

    /**
     * @var list<array{string, string, array<int, array{StoredTable, Heap}>}> The savepoints of the open transaction in the order they were set: the key of the name, the name, and the rows of each table first changed after the savepoint as they were at it
     */
    public array $savepoints = [];

    /**
     * The state of the XA transaction of the session.
     */
    public XaState $xa = XaState::NonExisting;

    /**
     * The key of the XID of the XA transaction of the session, as PreparedBranch::key answers it, or null when there is none.
     */
    public ?string $xid = null;

    /**
     * @var array<int, array{StoredTable, Heap}> The rows of each table before the current statement changed it
     */
    private array $statement = [];

    /**
     * @var array<int, array{StoredTable, Heap}> The rows of each table before the open transaction changed it
     */
    private array $kept = [];

    /**
     * @param Dictionary $dictionary The databases of the server
     */
    public function __construct(public readonly Dictionary $dictionary)
    {
    }

    /**
     * Keeps the rows of a table before a statement changes it.
     */
    public function touch(StoredTable $table): void
    {
        $id = spl_object_id($table);
        $this->statement[$id] ??= [$table, $table->data->copy()];
        if ($this->open) {
            $this->kept[$id] ??= [$table, $table->data->copy()];
            foreach (array_keys($this->savepoints) as $index) {
                $this->savepoints[$index][2][$id] ??= [$table, $table->data->copy()];
            }
        }
    }

    /**
     * Starts a statement.
     */
    public function beginStatement(): void
    {
        $this->statement = [];
    }

    /**
     * Ends a statement that succeeded.
     */
    public function endStatement(): void
    {
        $this->statement = [];
    }

    /**
     * Restores the tables a failed statement changed.
     */
    public function abortStatement(): void
    {
        foreach ($this->statement as [$table, $data]) {
            $table->data = $data;
        }
        $this->statement = [];
    }

    /**
     * Opens an explicit transaction, committing one that is open.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function begin(): void
    {
        $this->guard();
        $this->kept = [];
        $this->savepoints = [];
        $this->open = true;
    }

    /**
     * Ends the open transaction, keeping its changes.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function commit(): void
    {
        $this->guard();
        $this->end();
    }

    /**
     * Ends the open transaction, restoring the rows its changes replaced.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function rollback(): void
    {
        $this->guard();
        $this->restore();
        $this->end();
    }

    /**
     * Refuses a statement that ends or opens a transaction while an XA transaction is active or idle.
     *
     * @throws SqlError When an XA transaction is active or idle
     */
    public function guard(): void
    {
        if ($this->xa !== XaState::NonExisting) {
            throw ErrorCode::XaWrongState->error($this->xa->value);
        }
    }

    /**
     * Restores the rows the changes of the open transaction replaced.
     */
    public function restore(): void
    {
        foreach ($this->kept as [$table, $data]) {
            $table->data = $data;
        }
    }

    /**
     * Ends the open transaction, plain or XA, without restoring anything.
     */
    public function end(): void
    {
        $this->kept = [];
        $this->savepoints = [];
        $this->open = false;
        $this->xa = XaState::NonExisting;
        $this->xid = null;
    }

    /**
     * Ends the open transaction as XA PREPARE does: the changed rows leave the tables, which get back the rows they had when it started.
     *
     * @return list<array{StoredTable, Heap}> Each table the transaction changed, with its rows after the changes
     */
    public function detach(): array
    {
        $changes = [];
        foreach ($this->kept as [$table, $data]) {
            $changes[] = [$table, $table->data];
            $table->data = $data;
        }
        $this->end();

        return $changes;
    }

    /**
     * Sets a savepoint, deleting an earlier one of the same name; outside an explicit transaction it sets nothing.
     */
    public function savepoint(string $name): void
    {
        if (!$this->open) {
            return;
        }
        $key = Registry::key($name);
        $this->savepoints = array_values(array_filter($this->savepoints, static fn (array $savepoint): bool => $savepoint[0] !== $key));
        $this->savepoints[] = [$key, $name, []];
    }

    /**
     * Answers the position of a savepoint, or null when the transaction has none of that name.
     */
    public function find(string $name): ?int
    {
        $key = Registry::key($name);
        foreach ($this->savepoints as $index => $savepoint) {
            if ($savepoint[0] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Restores the rows the tables had at a savepoint and deletes the savepoints set after it.
     *
     * @throws SqlError When the transaction has no savepoint of the name
     */
    public function rollbackTo(string $name): void
    {
        $index = $this->find($name) ?? throw ErrorCode::RoutineMissing->error('SAVEPOINT', $name);
        foreach ($this->savepoints[$index][2] as [$table, $data]) {
            $table->data = $data;
        }
        $this->savepoints = array_slice($this->savepoints, 0, $index + 1);
        $this->savepoints[$index][2] = [];
    }

    /**
     * Deletes a savepoint and the savepoints set after it.
     *
     * @throws SqlError When the transaction has no savepoint of the name
     */
    public function release(string $name): void
    {
        $index = $this->find($name) ?? throw ErrorCode::RoutineMissing->error('SAVEPOINT', $name);
        $this->savepoints = array_slice($this->savepoints, 0, $index);
    }
}
