<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Aggregate\Accumulator;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Groups the rows of its input and answers one row per group: its first row and its aggregates.
 *
 * Groups are answered in the order of their grouping values when the path says the server groups
 * by sorting or reads an index, except that groups of a JSON value come in the order they first
 * appear, as the server groups them in a temporary table; otherwise in the order they first
 * appear (verified on a live 8.4 server); without grouping
 * expressions there is exactly one group. WITH ROLLUP the
 * super-aggregate rows follow the groups they fold.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 *
 * @visibility MySqlMemory
 */
final class AggregateIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    private bool $loaded = false;

    private Frame $frame;

    /**
     * @param Aggregate $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Aggregate $path, public readonly RowIterator $input)
    {
    }

    /**
     * Resets the groups without reading input that an outer filter or zero limit may discard.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->rows = [];
        $this->next = 0;
        $this->loaded = false;
    }

    /**
     * Reads the whole input and folds the groups when the first output row is requested.
     */
    public function gather(Frame $frame): void
    {
        $this->input->init($frame);
        foreach ($this->path->aggregates as $aggregate) {
            $aggregate->input?->enabled($frame);
        }
        $groups = [];
        $inputRows = 0;
        while (($row = $this->input->read()) !== null) {
            $inputRows++;
            $frame->row = $row;
            $values = [];
            $key = '';
            foreach ($this->path->groups as $group) {
                $value = $group->evaluate($frame);
                $values[] = $value;
                $key .= Order::key($value, $group->domain(), $this->path->hashed) . "\0";
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [$row, $values, array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates), []];
            }
            foreach ($this->path->rollup || ($this->path->sorted && !$this->path->hashed) ? [] : $groups[$key][2] as $accumulator) {
                $accumulator->add($frame);
            }
            if ($this->path->rollup || ($this->path->sorted && !$this->path->hashed)) {
                $groups[$key][3][] = $row;
            }
        }
        if ($groups === [] && $this->path->groups === []) {
            $groups[''] = [array_fill(0, $this->path->input->width(), null), [], array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates), []];
        }
        $sorted = $this->sorted(array_values($groups));
        $this->rows = $this->path->rollup ? $this->rollup($sorted, $frame) : $this->emit($sorted, $frame);
        $frame->context->aggregateRow = $inputRows + 1;
        $this->next = 0;
    }

    /**
     * Orders groups by their grouping values.
     *
     * @template T of array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>, ...}
     * @param list<T> $groups
     * @return list<T>
     */
    public function sorted(array $groups): array
    {
        $expressions = $this->path->groups;
        if (!$this->path->sorted) {
            return $groups;
        }
        foreach ($expressions as $expression) {
            if ($expression->domain()->kind === Kind::Json) {
                return $groups;
            }
        }
        $directions = $this->path->directions;
        usort($groups, static function (array $left, array $right) use ($expressions, $directions): int {
            foreach ($expressions as $index => $expression) {
                $order = Order::compare($left[1][$index], $right[1][$index], $expression->domain());
                if ($order !== 0) {
                    return ($directions[$index] ?? false) ? -$order : $order;
                }
            }

            return 0;
        });

        return $groups;
    }

    /**
     * Answers the output row of each group.
     *
     * @param list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>, list<list<int|float|string|null>>}> $groups
     * @return list<list<int|float|string|null>>
     */
    public function emit(array $groups, Frame $frame): array
    {
        $rows = [];
        foreach ($groups as [$row, , $accumulators, $members]) {
            foreach ($members as $member) {
                $frame->row = $member;
                foreach ($accumulators as $accumulator) {
                    $accumulator->add($frame);
                }
            }
            $frame->row = $row;
            $results = [];
            foreach ($accumulators as $accumulator) {
                $results[] = $accumulator->result($frame);
            }
            $rows[] = [...$row, ...$results];
        }

        return $rows;
    }

    /**
     * Answers the output rows of the groups and of their super-aggregates, in the order the server answers them WITH ROLLUP.
     *
     * After each group come the super-aggregate rows of the runs of groups it ends, from the run
     * that agrees on all but the last grouping value to the run of every group. A super-aggregate
     * row folds every row of its run and reads the other columns from the first row of the last
     * group of the run, with NULL in the columns of the grouping expressions it rolls up unless a
     * grouping expression it keeps is the same column.
     *
     * @param list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>, list<list<int|float|string|null>>}> $groups
     * @return list<list<int|float|string|null>>
     */
    public function rollup(array $groups, Frame $frame): array
    {
        $expressions = $this->path->groups;
        $count = count($expressions);
        if ($groups === [] && $this->path->emptyTotal) {
            return [$this->row(array_fill(0, $this->path->input->width(), null), array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates), array_fill(0, $count, null), $count, $frame)];
        }
        $runs = [];
        $rows = [];
        foreach ($groups as $index => [$first, $values, $accumulators, $members]) {
            for ($prefix = 0; $prefix < $count; $prefix++) {
                $runs[$prefix] ??= array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates);
            }
            $this->foldRollup($runs, $accumulators, $members, $frame);
            $rows[] = $this->row($first, $accumulators, $values, 0, $frame);
            $next = $groups[$index + 1][1] ?? null;
            for ($prefix = $count - 1; $prefix >= 0; $prefix--) {
                if ($next !== null && $this->agree($values, $next, $prefix)) {
                    break;
                }
                $rolled = $first;
                $kept = [];
                $grouped = array_slice($this->path->rollupColumns, 0, $prefix);
                foreach ($values as $position => $value) {
                    $kept[] = $position < $prefix ? $value : null;
                    $column = $this->path->rollupColumns[$position] ?? null;
                    if ($position >= $prefix && $column !== null && !in_array($column, $grouped, true)) {
                        $rolled[$column] = null;
                    }
                }
                $rows[] = $this->row(array_values($rolled), $runs[$prefix] ?? [], $kept, $count - $prefix, $frame);
                unset($runs[$prefix]);
            }
        }

        return $rows;
    }

    /**
     * Folds each input through the total, subtotals and leaf before the next aggregate.
     * Legacy plans evaluate every aggregate of the leaf first, then every aggregate
     * of each subtotal, ending with the grand total. Shared tuple inputs are captured
     * at the leaf and reused across levels.
     * Verified with variable assignments in SQL on MySQL 5.6, 5.7 and 8.4.
     *
     * @param array<int, list<Accumulator>> $runs Active subtotal accumulators by prefix
     * @param list<Accumulator> $leaf The current group's accumulators
     * @param list<list<int|float|string|null>> $members The group's input rows
     */
    public function foldRollup(array $runs, array $leaf, array $members, Frame $frame): void
    {
        foreach ($members as $member) {
            $frame->row = $member;
            if ($this->path->interleave) {
                foreach ($leaf as $position => $accumulator) {
                    $stored = (new \MySqlMemory\Evaluation\Aggregate\RollupInput())->capture($accumulator->accumulation, $frame);
                    foreach ($runs as $run) {
                        $run[$position]->add($frame, $stored);
                    }
                    $accumulator->add($frame, $stored);
                }
                continue;
            }
            $stored = [];
            foreach ($leaf as $position => $accumulator) {
                $stored[$position] = (new \MySqlMemory\Evaluation\Aggregate\RollupInput())->capture($accumulator->accumulation, $frame);
                $accumulator->add($frame, $stored[$position]);
            }
            foreach (array_reverse($runs) as $run) {
                foreach ($run as $position => $accumulator) {
                    $accumulator->add($frame, $stored[$position]);
                }
            }
        }
    }

    /**
     * Tells whether two groups agree on their leading grouping values.
     *
     * @param list<int|float|string|null> $left
     * @param list<int|float|string|null> $right
     */
    public function agree(array $left, array $right, int $prefix): bool
    {
        for ($position = 0; $position < $prefix; $position++) {
            if (Order::compare($left[$position], $right[$position], $this->path->groups[$position]->domain()) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers one output row WITH ROLLUP: the row, the aggregates, the grouping values and the number of grouping expressions rolled up.
     *
     * @param list<int|float|string|null> $row
     * @param list<Accumulator> $accumulators
     * @param list<int|float|string|null> $values
     * @return list<int|float|string|null>
     */
    public function row(array $row, array $accumulators, array $values, int $level, Frame $frame): array
    {
        $frame->row = $row;
        $results = [];
        foreach ($accumulators as $accumulator) {
            $results[] = $accumulator->result($frame);
        }

        return [...$row, ...$results, ...$values, $level];
    }

    /**
     * Answers the next group.
     */
    #[Override]
    public function read(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $this->gather($this->frame);
        }

        $row = $this->rows[$this->next++] ?? null;
        if ($row !== null) {
            $this->frame->row = $row;
        }

        return $row;
    }
}
