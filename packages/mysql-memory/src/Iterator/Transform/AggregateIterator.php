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
 * Groups are answered in the order of their grouping values, as the server returns them when it
 * groups by sorting, except that groups of a JSON value come in the order they first appear, as
 * the server groups them in a temporary table (verified on a live 8.4 server); without grouping
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

    /**
     * @param Aggregate $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Aggregate $path, public readonly RowIterator $input)
    {
    }

    /**
     * Reads the whole input and folds the groups.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->input->init($frame);
        $groups = [];
        while (($row = $this->input->read()) !== null) {
            $frame->row = $row;
            $values = [];
            $key = '';
            foreach ($this->path->groups as $group) {
                $value = $group->evaluate($frame);
                $values[] = $value;
                $key .= Order::key($value, $group->domain()) . "\0";
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [$row, $values, array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates), []];
            }
            foreach ($groups[$key][2] as $accumulator) {
                $accumulator->add($frame);
            }
            if ($this->path->rollup) {
                $groups[$key][3][] = $row;
            }
        }
        if ($groups === [] && $this->path->groups === []) {
            $groups[''] = [array_fill(0, $this->path->input->width(), null), [], array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates), []];
        }
        $sorted = $this->sorted(array_values($groups));
        $this->rows = $this->path->rollup ? $this->rollup($sorted, $frame) : $this->emit($sorted, $frame);
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
        foreach ($expressions as $expression) {
            if ($expression->domain()->kind === Kind::Json) {
                return $groups;
            }
        }
        usort($groups, static function (array $left, array $right) use ($expressions): int {
            foreach ($expressions as $index => $expression) {
                $order = Order::compare($left[1][$index], $right[1][$index], $expression->domain());
                if ($order !== 0) {
                    return $order;
                }
            }

            return 0;
        });

        return $groups;
    }

    /**
     * Answers the output row of each group.
     *
     * @param list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>, ...}> $groups
     * @return list<list<int|float|string|null>>
     */
    public function emit(array $groups, Frame $frame): array
    {
        $rows = [];
        foreach ($groups as [$row, , $accumulators]) {
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
        $runs = [];
        $rows = [];
        foreach ($groups as $index => [$first, $values, $accumulators, $members]) {
            for ($prefix = 0; $prefix < $count; $prefix++) {
                $runs[$prefix] ??= array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates);
            }
            foreach ($members as $member) {
                $frame->row = $member;
                foreach ($runs as $run) {
                    foreach ($run as $accumulator) {
                        $accumulator->add($frame);
                    }
                }
            }
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
        return $this->rows[$this->next++] ?? null;
    }
}
