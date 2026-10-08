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
 * Reads the whole input with the arguments of the window functions, sorts it for a window, and answers each row followed by the values of the window functions.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 *
 * @visibility MySqlMemory
 */
final class WindowIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param Window $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Window $path, public readonly RowIterator $input)
    {
    }

    /**
     * Reads and sorts the input, and computes the window functions partition by partition.
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
            $rows[] = [...$row, ...array_map(static fn ($argument) => $argument->evaluate($frame), $this->path->arguments)];
            $partitions[] = array_map(static fn ($expression) => $expression->evaluate($frame), $this->path->partition);
            $keys[] = array_map(static fn (array $key) => $key[0]->evaluate($frame), $this->path->order);
        }
        $this->rows = [];
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
     * @param list<list<int|float|string|null>> $partitions
     * @param list<list<int|float|string|null>> $keys
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
                $compared = Order::compare($partitions[$left][$position], $partitions[$right][$position], $expression->domain());
                if ($compared !== 0) {
                    return $compared;
                }
            }
            foreach ($order as $position => [$expression, $descending]) {
                $compared = Order::compare($keys[$left][$position], $keys[$right][$position], $expression->domain());
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
     * @param list<int|float|string|null> $left
     * @param list<int|float|string|null> $right
     */
    public function same(array $left, array $right): bool
    {
        foreach ($this->path->partition as $position => $expression) {
            if (Order::compare($left[$position], $right[$position], $expression->domain()) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Computes the window functions over the rows of one partition and adds the rows to the output.
     *
     * @param list<int> $members The indexes of the rows of the partition, in the order of the window
     * @param list<list<int|float|string|null>> $rows
     * @param list<list<int|float|string|null>> $keys
     * @throws \MySqlMemory\Error\SqlError When computing a value is an error
     */
    public function emit(array $members, array $rows, array $keys, Frame $frame): void
    {
        if ($members === []) {
            return;
        }
        $partition = new Partition(array_map(static fn (int $index): array => $rows[$index], $members), array_map(static fn (int $index): array => $keys[$index], $members), $this->path);
        $width = $this->path->input->width();
        foreach ($partition->rows as $position => $row) {
            $this->rows[] = [...array_slice($row, 0, $width), ...$partition->values($position, $frame)];
        }
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
