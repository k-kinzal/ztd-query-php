<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

use MySqlMemory\Dictionary\StoredTable;

/**
 * The row locks of the transactions of one server, the waits between them, and the deadlocks those waits form.
 *
 * A transaction locks whole rows: the rows a locking read returns and the rows a write changes,
 * shared or exclusive, until it ends. A transaction that wants a lock another transaction holds
 * in an incompatible mode waits for that transaction. A wait that would close a cycle of waits is
 * a deadlock. InnoDB then rolls back the lighter transaction of the cycle, its weight being the
 * rows it changed and the locks it holds: one table lock and one group of row locks for each table
 * and mode it locks rows in, and the lock it waits for. Between transactions of equal weight the
 * one whose request closed the cycle is rolled back (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlock-detection.html.
 *
 * @visibility MySqlMemory
 */
final class RowLocks
{
    /**
     * @var array<int, array<int, array<int, LockMode>>> The mode each transaction holds on each row, by table, row number and transaction
     */
    public array $held = [];

    /**
     * @var array<int, array<int, array<int, LockMode>>> The rows each transaction holds locks on, by transaction, table and row number
     */
    public array $owned = [];

    /**
     * @var array<int, StoredTable> The tables rows are locked in, by object id, kept while a lock is held
     */
    public array $tables = [];

    /**
     * @var array<int, list<int>> The transactions each waiting transaction waits for, by its id
     */
    public array $waits = [];

    /**
     * @var array<int, true> The waiting transactions a deadlock chose to roll back, by id
     */
    public array $victims = [];

    /**
     * Answers the transactions whose locks on a row keep a transaction from locking it in a mode.
     *
     * @return list<int>
     */
    public function blockers(StoredTable $table, int $row, int $owner, LockMode $mode): array
    {
        $blockers = [];
        foreach ($this->held[spl_object_id($table)][$row] ?? [] as $holder => $held) {
            if ($holder !== $owner && !$held->admits($mode)) {
                $blockers[] = $holder;
            }
        }

        return $blockers;
    }

    /**
     * Tells whether a transaction holds a lock on a row that includes a mode.
     */
    public function holds(StoredTable $table, int $row, int $owner, LockMode $mode): bool
    {
        return ($this->held[spl_object_id($table)][$row][$owner] ?? null)?->covers($mode) ?? false;
    }

    /**
     * Gives a transaction a lock on a row, keeping a stronger lock it holds.
     */
    public function grant(StoredTable $table, int $row, int $owner, LockMode $mode): void
    {
        $id = spl_object_id($table);
        if ($this->holds($table, $row, $owner, $mode)) {
            return;
        }
        $this->tables[$id] = $table;
        $this->held[$id][$row][$owner] = $mode;
        $this->owned[$owner][$id][$row] = $mode;
    }

    /**
     * Releases every lock of a transaction, and ends its wait.
     */
    public function release(int $owner): void
    {
        foreach ($this->owned[$owner] ?? [] as $id => $rows) {
            foreach (array_keys($rows) as $row) {
                unset($this->held[$id][$row][$owner]);
                if ($this->held[$id][$row] === []) {
                    unset($this->held[$id][$row]);
                }
            }
            if ($this->held[$id] === []) {
                unset($this->held[$id], $this->tables[$id]);
            }
        }
        unset($this->owned[$owner], $this->waits[$owner], $this->victims[$owner]);
    }

    /**
     * Answers the lock structures a transaction holds, as InnoDB counts them in its weight: a table lock and a group of row locks for each table and mode.
     */
    public function groups(int $owner): int
    {
        $groups = [];
        foreach ($this->owned[$owner] ?? [] as $id => $rows) {
            foreach ($rows as $mode) {
                $groups[$id . ($mode === LockMode::Shared ? 'S' : 'X')] = true;
            }
        }

        return 2 * count($groups) + (isset($this->waits[$owner]) ? 1 : 0);
    }

    /**
     * Answers the transactions of the cycle of waits a transaction would close by waiting for some others, itself included; empty when it closes none.
     *
     * @param list<int> $blockers The transactions it would wait for
     * @return list<int>
     */
    public function cycle(int $requester, array $blockers): array
    {
        $paths = array_map(static fn (int $blocker): array => [$blocker], $blockers);
        $seen = [];
        while ($paths !== []) {
            $path = array_shift($paths);
            $last = $path[count($path) - 1];
            if ($last === $requester) {
                return [$requester, ...array_slice($path, 0, -1)];
            }
            if (isset($seen[$last])) {
                continue;
            }
            $seen[$last] = true;
            foreach ($this->waits[$last] ?? [] as $next) {
                $paths[] = [...$path, $next];
            }
        }

        return [];
    }
}
