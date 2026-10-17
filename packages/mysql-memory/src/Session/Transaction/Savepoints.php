<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Transaction;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Session\Transaction;

/**
 * The savepoints of the open transaction of a session.
 *
 * Savepoint names compare without regard to letter case and accents. Without a transaction that
 * spans statements, SAVEPOINT sets nothing. ROLLBACK TO SAVEPOINT restores the rows changed after
 * the savepoint, keeping their locks, and deletes the savepoints set after it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 *
 * @visibility MySqlMemory
 */
final class Savepoints
{
    /**
     * @var list<array{string, string, int, int}> The savepoints in the order they were set: the key of the name, the name, the length of the undo log at the savepoint, and the changes to tables that are not transactional made before it
     */
    public array $list = [];

    /**
     * @param Transaction $transaction The transaction the savepoints are set in
     */
    public function __construct(public readonly Transaction $transaction)
    {
    }

    /**
     * Sets a savepoint, deleting an earlier one of the same name; without a transaction that spans statements it sets nothing.
     */
    public function set(string $name): void
    {
        if (!$this->transaction->open) {
            return;
        }
        $key = Registry::key($name);
        $this->list = array_values(array_filter($this->list, static fn (array $savepoint): bool => $savepoint[0] !== $key));
        $this->list[] = [$key, $name, count($this->transaction->undo), $this->transaction->untracked];
    }

    /**
     * Answers the position of a savepoint, or null when the transaction has none of that name.
     */
    public function find(string $name): ?int
    {
        $key = Registry::key($name);
        foreach ($this->list as $index => $savepoint) {
            if ($savepoint[0] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Restores the rows the tables had at a savepoint and deletes the savepoints set after it; the rows stay locked. Answers whether tables that are not transactional changed after the savepoint, which keep their changes.
     *
     * @throws SqlError When the transaction has no savepoint of the name
     */
    public function rollbackTo(string $name): bool
    {
        $index = $this->find($name) ?? throw ProgramError::RoutineMissing->error('SAVEPOINT', $name);
        $this->transaction->statements->undoFrom(min($this->list[$index][2], count($this->transaction->undo)), false);
        $this->list = array_slice($this->list, 0, $index + 1);

        return $this->transaction->untracked > $this->list[$index][3];
    }

    /**
     * Deletes a savepoint and the savepoints set after it.
     *
     * @throws SqlError When the transaction has no savepoint of the name
     */
    public function release(string $name): void
    {
        $index = $this->find($name) ?? throw ProgramError::RoutineMissing->error('SAVEPOINT', $name);
        $this->list = array_slice($this->list, 0, $index);
    }
}
