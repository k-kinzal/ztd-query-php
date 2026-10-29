<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Window\Partition;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Value\Order;
use Override;

/**
 * Sorts window input, then evaluates its arguments and outputs as rows are requested.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 *
 * @visibility MySqlMemory
 */
final class WindowIterator implements RowIterator
{
    /**
     * @var list<array{Partition, Frame}>
     */
    private array $partitions = [];

    private int $partition = 0;

    private int $next = 0;

    /**
     * @param Window $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Window $path, public readonly RowIterator $input)
    {
    }

    /**
     * Reads and sorts the input; window arguments and outputs are consumed by read().
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->input->init($frame);
        $rows = [];
        $partitions = [];
        $keys = [];
        while (($row = $this->input->read()) !== null) {
            $frame->row = $row;
            $rows[] = $row;
            $partitions[] = array_map(static fn ($expression) => $expression->evaluate($frame), $this->path->partition);
            $keys[] = array_map(static fn (array $key) => $key[0]->evaluate($frame), $this->path->order);
        }
        $this->partitions = [];
        $this->partition = 0;
        $this->next = 0;
        $members = [];
        $previous = null;
        foreach ($this->sorted($partitions, $keys) as $index) {
            if ($previous !== null && !$this->same($partitions[$previous], $partitions[$index])) {
                $this->emit($members, $rows, $keys, $frame);
                $members = [];
            }
            $members[] = $index;
            $previous = $index;
        }
        $this->emit($members, $rows, $keys, $frame);
    }

    /**
     * Answers the indexes of the input rows in the order of the window: by partition, then by the ORDER BY expressions; ties keep input order.
     *
     * @param list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>> $partitions
     * @param list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>> $keys
     * @return list<int>
     */
    public function sorted(array $partitions, array $keys): array
    {
        $indexes = array_keys($partitions);
        if ($this->path->partition === [] && $this->path->order === []) {
            return $indexes;
        }
        $partition = $this->path->partition;
        $order = $this->path->order;
        usort($indexes, static function (int $left, int $right) use ($partitions, $keys, $partition, $order): int {
            foreach ($partition as $position => $expression) {
                $compared = Order::compare(\MySqlMemory\Evaluation\ExactDecimal::raw($partitions[$left][$position]), \MySqlMemory\Evaluation\ExactDecimal::raw($partitions[$right][$position]), $expression->domain());
                if ($compared !== 0) {
                    return $compared;
                }
            }
            foreach ($order as $position => [$expression, $descending]) {
                $compared = Order::compare(\MySqlMemory\Evaluation\ExactDecimal::raw($keys[$left][$position]), \MySqlMemory\Evaluation\ExactDecimal::raw($keys[$right][$position]), $expression->domain());
                if ($compared !== 0) {
                    return $descending ? -$compared : $compared;
                }
            }

            return $left <=> $right;
        });

        return $indexes;
    }

    /**
     * Tells whether two rows are in the same partition.
     *
     * @param list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null> $left
     * @param list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null> $right
     */
    public function same(array $left, array $right): bool
    {
        foreach ($this->path->partition as $position => $expression) {
            if (Order::compare(\MySqlMemory\Evaluation\ExactDecimal::raw($left[$position]), \MySqlMemory\Evaluation\ExactDecimal::raw($right[$position]), $expression->domain()) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Registers one partition for demand-driven argument and output evaluation.
     *
     * @param list<int> $members The indexes of the rows of the partition, in the order of the window
     * @param list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>> $rows
     * @param list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>> $keys
     */
    public function emit(array $members, array $rows, array $keys, Frame $frame): void
    {
        if ($members === []) {
            return;
        }
        $partition = new Partition(array_map(static fn (int $index): array => $rows[$index], $members), array_map(static fn (int $index): array => $keys[$index], $members), $this->path);
        $this->partitions[] = [$partition, $frame];
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        while (isset($this->partitions[$this->partition])) {
            [$partition, $frame] = $this->partitions[$this->partition];
            if (!isset($partition->rows[$this->next])) {
                $this->partition++;
                $this->next = 0;
                continue;
            }
            $position = $this->next++;
            $frame->row = $partition->rows[$position];
            $partition->load($partition->frontier($position, $frame), $frame);
            $captured = $partition;
            if ($partition->lookahead($position) && isset($this->partitions[$this->partition + 1])) {
                $captured = $this->partitions[$this->partition + 1][0];
                $captured->load(0, $frame);
            }
            $this->capture($captured, $frame);
            $values = $partition->values($position, $frame);
            $width = $this->path->input->width();
            foreach ($values as $index => $value) {
                $frame->windowSlots[$width + $index] = $value;
            }

            return [...array_slice($partition->rows[$position], 0, $width), ...$values, ...array_slice($partition->rows[$position], $width + count($this->path->arguments))];
        }

        return null;
    }

    /**
     * Publishes input fields separately from window outputs, including a lookahead row.
     * Negative positions keep argument fields distinct from the visible output positions.
     */
    public function capture(Partition $partition, Frame $frame): void
    {
        $width = $this->path->input->width();
        foreach ($partition->captured() as $index => $value) {
            $slot = $index < count($this->path->arguments) ? -$width - $index - 1 : $width + count($this->path->functions) + $index - count($this->path->arguments);
            $frame->windowSlots[$slot] = $value;
        }
    }

}
