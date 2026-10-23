<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Transaction;

use Closure;
use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Transaction;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;

/**
 * How a transaction reaches the rows of InnoDB tables: what its reads see, and the row locks its reads and writes take.
 *
 * A consistent read of REPEATABLE READ sees the snapshot the first consistent read of the
 * transaction took, or START TRANSACTION WITH CONSISTENT SNAPSHOT; READ COMMITTED takes a snapshot
 * for each statement; READ UNCOMMITTED reads the latest rows. Locking reads, and plain reads of
 * SERIALIZABLE inside a transaction, read the latest committed rows and lock the rows they return.
 * A transaction that wants a lock another transaction holds waits for it, until the lock is free,
 * the wait times out, or the wait closes a cycle of waits, a deadlock.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-consistent-read.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-parameters.html#sysvar_innodb_lock_wait_timeout.
 *
 * @visibility MySqlMemory
 */
final class RowAccess
{
    /**
     * Restores what the session sets for the statement it runs, once a statement that waited for a lock goes on, or null.
     */
    public ?Closure $resumed = null;

    /**
     * @param Transaction $transaction The transaction that reads and locks the rows
     */
    public function __construct(public readonly Transaction $transaction)
    {
    }

    /**
     * Answers the rows of a table a read of the statement sees, by row number in row number order.
     *
     * A consistent read sees its snapshot, or the latest rows under READ UNCOMMITTED; a locking
     * read goes through the latest rows. A read of an InnoDB table makes the transaction active.
     * The rows of a temporary table and of a table of another engine are read as they are.
     *
     * @param LockMode|null $locking The lock a locking read takes, or null for a consistent read
     * @return array<int, list<int|float|string|null>>
     */
    public function rows(StoredTable $table, ?LockMode $locking = null): array
    {
        $transaction = $this->transaction;
        if (!Transaction::transactional($table)) {
            return $table->data->rows;
        }
        $transaction->engaged = true;
        if ($table->definition->temporary) {
            return $table->data->rows;
        }
        if ($locking !== null) {
            return $transaction->system->latest($table, $transaction);
        }
        if ($transaction->isolation === Isolation::ReadUncommitted) {
            return $table->data->rows;
        }
        $transaction->snapshot ??= $transaction->system->open($transaction->id);

        return $transaction->system->visible($table, $transaction, $transaction->snapshot);
    }

    /**
     * Answers the committed version of a row another open transaction changed, as a list holding the row or null when the row did not exist then; null when no other transaction changed it.
     *
     * @return array{list<int|float|string|null>|null}|null
     */
    public function committed(StoredTable $table, int $number): ?array
    {
        return Transaction::transactional($table) && !$table->definition->temporary ? $this->transaction->system->committed($table, $number, $this->transaction) : null;
    }

    /**
     * Tells whether another transaction holds a lock on a row that keeps the transaction from locking it in a mode.
     */
    public function contended(StoredTable $table, int $number, LockMode $mode): bool
    {
        return Transaction::transactional($table) && !$table->definition->temporary && $this->transaction->system->locks->blockers($table, $number, $this->transaction->id, $mode) !== [];
    }

    /**
     * Locks a row in a mode, waiting for the transactions that hold it, and answers whether it holds the lock; SKIP LOCKED answers false for a row another transaction holds.
     *
     * A wait ends when the lock is free, or after innodb_lock_wait_timeout seconds with
     * ER_LOCK_WAIT_TIMEOUT, which rolls back the statement. NOWAIT refuses the row at once with
     * ER_LOCK_NOWAIT.
     *
     * @throws SqlError When the lock cannot be taken
     */
    public function lock(StoredTable $table, int $number, LockMode $mode, ?LockedRowAction $action = null): bool
    {
        if (!Transaction::transactional($table) || $table->definition->temporary) {
            return true;
        }
        $locks = $this->transaction->system->locks;
        $deadline = null;
        for (;;) {
            $blockers = $locks->blockers($table, $number, $this->transaction->id, $mode);
            if ($blockers === []) {
                $locks->grant($table, $number, $this->transaction->id, $mode);
                $this->transaction->engaged = true;

                return true;
            }
            if ($action === LockedRowAction::Nowait) {
                throw TransactionError::LockNowait->error();
            }
            if ($action === LockedRowAction::SkipLocked) {
                return false;
            }
            $deadline = $this->wait($blockers, $deadline);
        }
    }

    /**
     * Waits once for the transactions that hold a lock the transaction wants, and answers when the whole wait times out.
     *
     * A wait that would close a cycle of waits is a deadlock: the lighter transaction of the cycle
     * is rolled back with ER_LOCK_DEADLOCK. Only a statement the listener runs can wait: elsewhere
     * nothing else runs while it would, so the wait times out at once, and the time it would have
     * taken passes on the clock of the server.
     *
     * @param list<int> $blockers The transactions that hold the lock
     * @param float|null $deadline When the wait times out, or null when it starts now
     *
     * @throws SqlError When the transaction is chosen as the victim of a deadlock, or the wait times out
     */
    public function wait(array $blockers, ?float $deadline): float
    {
        $transaction = $this->transaction;
        $locks = $transaction->system->locks;
        $cycle = $locks->cycle($transaction->id, $blockers);
        if ($cycle !== []) {
            $victim = $transaction->system->victim($cycle);
            if ($victim === $transaction->id) {
                $this->deadlock();
            }
            $locks->victims[$victim] = true;
        }
        $timeout = max(1, (int) ($transaction->variables?->read('innodb_lock_wait_timeout') ?? 50));
        if (!$transaction->system->scheduler->suspendable()) {
            $transaction->variables?->instance->registry->threads->pass($timeout);
            throw TransactionError::LockWaitTimeout->error();
        }
        $deadline ??= microtime(true) + $timeout;
        $locks->waits[$transaction->id] = $blockers;
        try {
            $transaction->system->scheduler->pause();
        } finally {
            unset($locks->waits[$transaction->id]);
            if ($this->resumed !== null) {
                ($this->resumed)();
            }
        }
        if (($transaction->variables?->instance->sessions[$transaction->id] ?? null)?->get()?->interrupted === true) {
            throw \MySqlMemory\Error\Family\StatementError::QueryInterrupted->error();
        }
        if (isset($locks->victims[$transaction->id])) {
            unset($locks->victims[$transaction->id]);
            $this->deadlock();
        }
        if (microtime(true) >= $deadline) {
            throw TransactionError::LockWaitTimeout->error();
        }

        return $deadline;
    }

    /**
     * Rolls back the whole transaction a deadlock chose, and raises ER_LOCK_DEADLOCK.
     *
     * @throws SqlError Always
     */
    public function deadlock(): never
    {
        $this->transaction->restore();
        $this->transaction->end();

        throw TransactionError::LockDeadlock->error();
    }

    /**
     * Answers the lock the plain reads of the statement take: shared under SERIALIZABLE inside a transaction that spans statements, else none.
     */
    public function plainReads(): ?LockMode
    {
        return $this->transaction->isolation === Isolation::Serializable && $this->transaction->open ? LockMode::Shared : null;
    }

    /**
     * Answers the lock the reads of a statement that writes take of the tables it does not write: shared under REPEATABLE READ and SERIALIZABLE, none under the other levels, which read them consistently.
     */
    public function sourceReads(): ?LockMode
    {
        $isolation = $this->transaction->isolation;

        return $isolation === Isolation::RepeatableRead || $isolation === Isolation::Serializable ? LockMode::Shared : null;
    }
}
