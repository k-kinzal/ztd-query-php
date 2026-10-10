<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Session\Transaction;
use MySqlMemory\Storage\Heap;

/**
 * The transactions of the sessions of one server, and the versions of the rows they read.
 *
 * A table holds the latest version of each row, changes no transaction has committed included.
 * The transaction that changed a row keeps its earlier version until it ends, and the row stays
 * locked by it meanwhile, so at most one open transaction has changed a row. Each commit that
 * changed rows takes the next number of a sequence. A consistent read sees the rows as the
 * commits up to a number of the sequence, its snapshot, left them, with the changes of its own
 * transaction: the earlier versions of the rows the commits after the snapshot and the open
 * transactions changed are kept for as long as a snapshot older than them is open. A locking read
 * sees the latest version of each row, and the rows other open transactions deleted, as they are
 * the rows it waits for.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-multi-versioning.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-consistent-read.html.
 *
 * @visibility MySqlMemory
 */
final class Transactions
{
    /**
     * The number of the last commit that changed rows.
     */
    public int $sequence = 0;

    /**
     * @var array<int, Transaction> The transaction of each session connected, by connection id
     */
    public array $sessions = [];

    /**
     * @var array<int, int> The snapshot of each open read view, by the id of its transaction
     */
    public array $views = [];

    /**
     * @var array<int, list<array{int, Heap, array<int, list<int|float|string|null>|null>}>> The commits an open snapshot does not see, by table: the number of the commit, the rows it changed, and the version of each before it
     */
    public array $history = [];

    /**
     * The row locks of the transactions.
     */
    public readonly RowLocks $locks;

    /**
     * Where a statement waits for a lock.
     */
    public readonly Scheduler $scheduler;

    /**
     * Builds the transactions of a server that has none.
     */
    public function __construct()
    {
        $this->locks = new RowLocks();
        $this->scheduler = new Scheduler();
    }

    /**
     * Answers the transaction of a session, or null when no session of that id is connected.
     */
    public function of(int $connection): ?Transaction
    {
        return $this->sessions[$connection] ?? null;
    }

    /**
     * Opens a read view for a transaction and answers its snapshot: the commits so far.
     */
    public function open(int $owner): int
    {
        return $this->views[$owner] = $this->sequence;
    }

    /**
     * Closes the read view of a transaction.
     */
    public function close(int $owner): void
    {
        unset($this->views[$owner]);
        $this->prune();
    }

    /**
     * Records the commit of a transaction that changed rows: it takes the next number, and the earlier versions of its rows are kept for the snapshots that do not see it.
     */
    public function commit(Transaction $transaction): void
    {
        $this->archive(array_map(static fn (array $change): array => [$change[0], $change[1]], $transaction->changed));
    }

    /**
     * Records a commit of changed rows, given the earlier version of each row by table, and answers its number.
     *
     * @param array<int, array{Heap, array<int, list<int|float|string|null>|null>}> $changes
     */
    public function archive(array $changes): int
    {
        $this->sequence++;
        if ($this->views !== []) {
            foreach ($changes as $id => [$heap, $before]) {
                $this->history[$id][] = [$this->sequence, $heap, $before];
            }
        }
        $this->prune();

        return $this->sequence;
    }

    /**
     * Commits the changes of a prepared XA branch: each row gets its version after the changes, and the commit takes the next number.
     *
     * A row of a table whose rows were replaced meanwhile, as TRUNCATE TABLE replaces them, is left out.
     *
     * @param list<array{StoredTable, Heap, int, list<int|float|string|null>|null}> $changes
     */
    public function apply(array $changes): void
    {
        $before = [];
        $committed = time();
        foreach ($changes as [$table, $heap, $number, $after]) {
            if ($table->data !== $heap) {
                continue;
            }
            $table->updated = $committed;
            $id = spl_object_id($table);
            $before[$id] ??= [$heap, []];
            if (!array_key_exists($number, $before[$id][1])) {
                $before[$id][1][$number] = $heap->rows[$number] ?? null;
            }
            if ($after === null) {
                unset($heap->rows[$number]);
            } else {
                $heap->rows[$number] = $after;
            }
        }
        foreach ($before as [$heap]) {
            ksort($heap->rows);
        }
        $this->archive($before);
    }

    /**
     * Forgets the earlier versions no open snapshot needs.
     */
    public function prune(): void
    {
        if ($this->views === []) {
            $this->history = [];

            return;
        }
        $oldest = min($this->views);
        foreach ($this->history as $id => $commits) {
            $kept = array_values(array_filter($commits, static fn (array $commit): bool => $commit[0] > $oldest));
            if ($kept === []) {
                unset($this->history[$id]);
            } else {
                $this->history[$id] = $kept;
            }
        }
    }

    /**
     * Answers the rows of a table a consistent read of a transaction sees, by row number in row number order.
     *
     * @return array<int, list<int|float|string|null>>
     */
    public function visible(StoredTable $table, Transaction $reader, int $snapshot): array
    {
        $rows = $table->data->rows;
        $heap = $table->data;
        $id = spl_object_id($table);
        $earlier = [];
        foreach ($this->history[$id] ?? [] as [$number, $changed, $before]) {
            if ($number > $snapshot && $changed === $heap) {
                $earlier += $before;
            }
        }
        foreach ($this->sessions as $other) {
            if ($other !== $reader && ($other->changed[$id][0] ?? null) === $heap) {
                $earlier += $other->changed[$id][1];
            }
        }
        if (($reader->changed[$id][0] ?? null) === $heap) {
            $earlier = array_diff_key($earlier, $reader->changed[$id][1]);
        }

        return $this->restored($rows, $earlier);
    }

    /**
     * Answers the rows of a table a locking read of a transaction goes through, by row number in row number order: the latest versions, and the rows other open transactions deleted.
     *
     * @return array<int, list<int|float|string|null>>
     */
    public function latest(StoredTable $table, Transaction $reader): array
    {
        $rows = $table->data->rows;
        $id = spl_object_id($table);
        $deleted = [];
        foreach ($this->sessions as $other) {
            if ($other !== $reader && ($other->changed[$id][0] ?? null) === $table->data) {
                foreach ($other->changed[$id][1] as $number => $row) {
                    if ($row !== null && !isset($rows[$number])) {
                        $deleted[$number] = $row;
                    }
                }
            }
        }

        return $this->restored($rows, $deleted);
    }

    /**
     * Answers the committed versions of the rows of a table other open transactions changed, null for those they inserted, by row number.
     *
     * @return array<int, list<int|float|string|null>|null>
     */
    public function earlier(StoredTable $table, Transaction $reader): array
    {
        $id = spl_object_id($table);
        $earlier = [];
        foreach ($this->sessions as $other) {
            if ($other !== $reader && ($other->changed[$id][0] ?? null) === $table->data) {
                $earlier += $other->changed[$id][1];
            }
        }

        return $earlier;
    }

    /**
     * Answers the committed version of a row another open transaction changed, as a list holding the row or null when the row did not exist; null when no other transaction changed it.
     *
     * @return array{list<int|float|string|null>|null}|null
     */
    public function committed(StoredTable $table, int $number, Transaction $reader): ?array
    {
        $id = spl_object_id($table);
        foreach ($this->sessions as $other) {
            if ($other !== $reader && ($other->changed[$id][0] ?? null) === $table->data && array_key_exists($number, $other->changed[$id][1])) {
                return [$other->changed[$id][1][$number]];
            }
        }

        return null;
    }

    /**
     * Chooses the transaction a deadlock rolls back: the lightest of the cycle, the requester between equals.
     *
     * @param list<int> $cycle The transactions of the cycle, the requester first
     */
    public function victim(array $cycle): int
    {
        $requester = $cycle[0];
        $victim = $requester;
        $lightest = $this->weight($requester) + 1;
        foreach (array_slice($cycle, 1) as $member) {
            $weight = $this->weight($member);
            if ($weight < $lightest) {
                [$victim, $lightest] = [$member, $weight];
            }
        }

        return $victim;
    }

    /**
     * Answers the weight of a transaction: the row changes it would undo and the lock structures it holds.
     */
    public function weight(int $owner): int
    {
        return count($this->sessions[$owner]->undo ?? []) + $this->locks->groups($owner);
    }

    /**
     * Puts earlier versions of rows back over the latest ones: a null version removes the row.
     *
     * @param array<int, list<int|float|string|null>> $rows
     * @param array<int, list<int|float|string|null>|null> $earlier
     * @return array<int, list<int|float|string|null>>
     */
    public function restored(array $rows, array $earlier): array
    {
        $added = false;
        foreach ($earlier as $number => $row) {
            if ($row === null) {
                unset($rows[$number]);
                continue;
            }
            $added = $added || !isset($rows[$number]);
            $rows[$number] = $row;
        }
        if ($added) {
            ksort($rows);
        }

        return $rows;
    }
}
