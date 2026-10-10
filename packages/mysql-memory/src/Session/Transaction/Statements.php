<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Transaction;

use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Session\Transaction;

/**
 * The statements a transaction runs, and the atomicity of each: a statement that fails puts back the rows it changed, as InnoDB rolls back a failed statement.
 *
 * A statement of a stored function or trigger runs inside the statement that invokes it, and its
 * changes are restored when that statement fails; the statements of a procedure CALL stand on
 * their own. With autocommit on, each statement outside BEGIN is a transaction of its own; with
 * autocommit off, a transaction starts with the first statement, and turning autocommit on
 * commits it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html.
 *
 * @visibility MySqlMemory
 */
final class Statements
{
    /**
     * @var list<array{int, bool, bool}> The statements running, the outermost first: the length of the undo log when each started, whether it changed a table its failure cannot restore, and whether autocommit was on then
     */
    public array $running = [];

    /**
     * @var array<int, true> The positions in the undo log of the changes a failed statement around them keeps, as the statements of a procedure CALL stand on their own
     */
    public array $kept = [];

    /**
     * @param Transaction $transaction The transaction the statements run in
     */
    public function __construct(public readonly Transaction $transaction)
    {
    }

    /**
     * Starts a statement, inside the statement that runs, if any: a statement of a stored function or trigger runs inside the statement that invokes it.
     *
     * A statement that runs on its own starts the transaction it is part of: the implicit
     * transaction of autocommit off, or a transaction of its own; under READ COMMITTED it reads a
     * snapshot of its own.
     *
     * @param bool $contained Whether the statement is part of the statement around it
     */
    public function begin(bool $contained = true): void
    {
        $transaction = $this->transaction;
        $autocommit = $transaction->autocommit();
        if ($this->running === [] || !$contained) {
            if (!$transaction->open) {
                $transaction->isolation = $transaction->nextIsolation ?? $transaction->sessionIsolation();
                $transaction->readOnly = $transaction->nextReadOnly ?? $transaction->sessionReadOnly();
                $transaction->open = !$autocommit;
                $transaction->forget();
            } elseif ($transaction->isolation === Isolation::ReadCommitted) {
                $transaction->forget();
            }
        }
        $this->running[] = [count($transaction->undo), false, $autocommit];
    }

    /**
     * Ends a statement that succeeded: the statement around it, if any, restores the rows the statement changed when it fails, unless the statement stands on its own, as a statement of a procedure CALL runs.
     *
     * A statement that runs on its own ends the transaction of its own autocommit gave it; one
     * that turned autocommit on commits the open transaction.
     *
     * @param bool $contained Whether the statement is part of the statement around it
     */
    public function end(bool $contained = true): void
    {
        [$mark, $unrestorable, $autocommit] = array_pop($this->running) ?? [count($this->transaction->undo), false, true];
        if ($this->running !== []) {
            $outer = count($this->running) - 1;
            $this->running[$outer][1] = $this->running[$outer][1] || $unrestorable;
        }
        if (!$contained) {
            for ($position = $mark; $position < count($this->transaction->undo); $position++) {
                $this->kept[$position] = true;
            }
        }
        if ($this->running === [] || !$contained) {
            $this->settle($autocommit);
        }
    }

    /**
     * Restores the rows a failed statement changed, and goes back to the statement around it, if any.
     *
     * The changes to tables that are not transactional stay; the statement then records that it
     * could not restore them.
     */
    public function abort(): void
    {
        $statement = array_pop($this->running);
        if ($statement === null) {
            return;
        }
        [$mark, $unrestorable, $autocommit] = $statement;
        $this->undoFrom(min($mark, count($this->transaction->undo)), true);
        $this->transaction->unrestored = $unrestorable;
        if ($this->running === []) {
            $this->settle($autocommit);
        }
    }

    /**
     * Records that the statement running changed a table its failure cannot restore.
     */
    public function unrestorable(): void
    {
        if ($this->running !== []) {
            $this->running[count($this->running) - 1][1] = true;
        }
    }

    /**
     * Ends the transaction of a statement that ran on its own, as autocommit ends it, or commits the open transaction a statement turning autocommit on leaves.
     */
    public function settle(bool $autocommit): void
    {
        $transaction = $this->transaction;
        if ($transaction->open && !$autocommit && $transaction->autocommit()) {
            $transaction->end();
        }
        if (!$transaction->open) {
            $transaction->end();
        }
    }

    /**
     * Puts back the rows the changes from a position of the undo log on replaced, the latest first, and forgets those changes; a statement keeps the changes of the statements that stood on their own.
     */
    public function undoFrom(int $mark, bool $statement): void
    {
        $undo = $this->transaction->undo;
        $remaining = array_slice($undo, 0, $mark);
        $kept = [];
        for ($position = count($undo) - 1; $position >= $mark; $position--) {
            if ($statement && isset($this->kept[$position])) {
                $kept[] = $undo[$position];
                continue;
            }
            [$table, $heap, $number, $before] = $undo[$position];
            if ($before === null) {
                unset($heap->rows[$number]);
            } else {
                $heap->rows[$number] = $before;
            }
        }
        $positions = [];
        foreach (array_keys($this->kept) as $position) {
            if ($position < $mark) {
                $positions[$position] = true;
            }
        }
        foreach (array_reverse($kept) as $change) {
            $positions[count($remaining)] = true;
            $remaining[] = $change;
        }
        $this->transaction->undo = $remaining;
        $this->kept = $positions;
    }
}
