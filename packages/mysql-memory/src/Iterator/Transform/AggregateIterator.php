<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Aggregate\Accumulator;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Value\Order;
use Override;

/**
 * Groups the rows of its input and answers one row per group: its first row and its aggregates.
 *
 * Groups are answered in the order of their grouping values, as the server returns them when it
 * groups by sorting; without grouping expressions there is exactly one group.
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
                $groups[$key] = [$row, $values, array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates)];
            }
            foreach ($groups[$key][2] as $accumulator) {
                $accumulator->add($frame);
            }
        }
        if ($groups === [] && $this->path->groups === []) {
            $groups[''] = [array_fill(0, $this->path->input->width(), null), [], array_map(static fn ($accumulation): Accumulator => $accumulation->start(), $this->path->aggregates)];
        }
        $this->rows = $this->emit($this->sorted(array_values($groups)), $frame);
        $this->next = 0;
    }

    /**
     * Orders groups by their grouping values.
     *
     * @param list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>}> $groups
     * @return list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>}>
     */
    public function sorted(array $groups): array
    {
        $expressions = $this->path->groups;
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
     * @param list<array{list<int|float|string|null>, list<int|float|string|null>, list<Accumulator>}> $groups
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
     * Answers the next group.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
