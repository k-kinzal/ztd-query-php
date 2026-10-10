<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Iterator\Source\TableScanIterator;
use MySqlMemory\Plan\Path\Transform\Lock;
use MySqlMemory\Session\Transaction;
use MySqlMemory\Storage\TimestampZones;
use Override;

/**
 * Passes the rows of a query block that meet its WHERE condition, locking the row of each locked table they hold.
 *
 * The locked tables are read through their latest rows. A row is locked once it meets the
 * condition; a row another transaction holds a lock on is waited for when it meets the condition
 * in its latest or in its committed version, and once it is locked its latest version is read
 * again and the condition checked again, as InnoDB reads a row again after a lock wait. NOWAIT
 * fails at such a row and SKIP LOCKED leaves it out. A row the block reads in the latest version
 * of a table and that another transaction holds no lock on is locked at once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 *
 * @visibility MySqlMemory
 */
final class LockIterator implements RowIterator
{
    private Frame $frame;

    private ?Transaction $transaction = null;

    /**
     * @var list<array{TableScanIterator, int, \MySqlMemory\Concurrency\LockMode, \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction|null}> The scans of the locked tables, with the offset of their columns, their lock mode and their action
     */
    private array $targets = [];

    /**
     * @param Lock $path The locking read executed
     * @param RowIterator $input The iterator of the rows of the FROM clause
     * @param Builder $builder The builder of the iterators, which holds those of the scans
     */
    public function __construct(public readonly Lock $path, public readonly RowIterator $input, Builder $builder)
    {
        foreach ($path->targets as [$scan, $offset, $mode, $action]) {
            $iterator = $builder->scans[spl_object_id($scan)] ?? null;
            if ($iterator instanceof TableScanIterator) {
                $iterator->locking = $mode;
                $this->targets[] = [$iterator, $offset, $mode, $action];
            }
        }
    }

    /**
     * Starts the input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $variables = $frame->context->variables;
        $this->transaction = $variables->instance->transactions->of($variables->connection);
        $this->input->init($frame);
    }

    /**
     * Answers the next row that meets the condition, once its rows are locked.
     *
     * @throws \MySqlMemory\Error\SqlError When a row cannot be locked
     */
    #[Override]
    public function read(): ?array
    {
        if (!$this->holds()) {
            return null;
        }
        $transaction = $this->transaction;
        while (($row = $this->input->read()) !== null) {
            if ($transaction === null) {
                if ($this->meets($row)) {
                    return $row;
                }
                continue;
            }
            $contended = [];
            foreach ($this->targets as $index => [$scan, $offset, $mode]) {
                if ($scan->current !== null && $transaction->access->contended($scan->path->table, $scan->current, $mode)) {
                    $contended[$index] = true;
                }
            }
            if (!$this->meets($row) && !$this->committedMeets($row, $contended)) {
                continue;
            }
            $locked = true;
            foreach ($this->targets as [$scan, , $mode, $action]) {
                if ($scan->current !== null && !$transaction->access->lock($scan->path->table, $scan->current, $mode, $action)) {
                    $locked = false;
                    break;
                }
            }
            if (!$locked) {
                continue;
            }
            if ($contended !== []) {
                $row = $this->latest($row);
                if ($row === null || !$this->meets($row)) {
                    continue;
                }
            }

            return $row;
        }

        return null;
    }

    /**
     * Tells whether the precondition of the WHERE condition holds, evaluated once for the statement, before the first row is read.
     */
    public function holds(): bool
    {
        $precondition = $this->path->filter?->precondition;
        if ($precondition === null) {
            return true;
        }
        $kept = $this->frame->context->kept;
        if (!isset($kept[$this->path])) {
            $kept[$this->path] = [Convert::toBool($precondition->evaluate($this->frame), $precondition->domain(), $this->frame->context) === true ? 1 : 0];
        }

        return $kept[$this->path][0] === 1;
    }

    /**
     * Tells whether a row meets the condition of the block.
     *
     * @param list<int|float|string|null> $row
     */
    public function meets(array $row): bool
    {
        $condition = $this->path->filter?->condition;
        if ($condition === null) {
            return true;
        }
        $this->frame->row = $row;

        return Convert::toBool($condition->evaluate($this->frame), $condition->domain(), $this->frame->context) === true;
    }

    /**
     * Tells whether a row meets the condition with the committed version of a row another transaction changed in place of its latest one.
     *
     * @param list<int|float|string|null> $row
     * @param array<int, true> $contended The targets whose rows another transaction holds, by position
     */
    public function committedMeets(array $row, array $contended): bool
    {
        foreach (array_keys($contended) as $index) {
            [$scan, $offset] = $this->targets[$index];
            $committed = $scan->current === null ? null : $this->transaction?->access->committed($scan->path->table, $scan->current);
            if ($committed !== null && $committed[0] !== null && $this->meets($this->spliced($row, $scan, $offset, $committed[0]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers a row with the latest version of the row of each locked table in place, or null when one was deleted.
     *
     * @param list<int|float|string|null> $row
     * @return list<int|float|string|null>|null
     */
    public function latest(array $row): ?array
    {
        foreach ($this->targets as [$scan, $offset]) {
            if ($scan->current === null) {
                continue;
            }
            $latest = $scan->path->table->data->rows[$scan->current] ?? null;
            if ($latest === null) {
                return null;
            }
            $row = $this->spliced($row, $scan, $offset, $latest);
        }

        return $row;
    }

    /**
     * Answers a row with a version of the row of a table in place of the one it holds.
     *
     * @param list<int|float|string|null> $row
     * @param list<int|float|string|null> $version
     * @return list<int|float|string|null>
     */
    public function spliced(array $row, TableScanIterator $scan, int $offset, array $version): array
    {
        $local = (new TimestampZones())->local($scan->path->table, $version, $this->frame->context);
        array_splice($row, $offset, count($local), $local);

        return $row;
    }
}
