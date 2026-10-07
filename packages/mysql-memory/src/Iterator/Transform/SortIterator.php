<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Value\Order;
use Override;

/**
 * Reads the whole input and answers its rows in the order of the sort keys; ties keep input order.
 *
 * @visibility MySqlMemory
 */
final class SortIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param Sort $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Sort $path, public readonly RowIterator $input)
    {
    }

    /**
     * Reads and sorts the input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->input->init($frame);
        $rows = [];
        while (($row = $this->input->read()) !== null) {
            $rows[] = $row;
        }
        $keys = $this->path->keys;
        $indexes = array_keys($rows);
        usort($indexes, static function (int $left, int $right) use ($rows, $keys): int {
            foreach ($keys as [$position, $domain, $descending]) {
                $order = Order::compare($rows[$left][$position], $rows[$right][$position], $domain);
                if ($order !== 0) {
                    return $descending ? -$order : $order;
                }
            }

            return $left <=> $right;
        });
        $this->rows = array_map(static fn (int $index): array => $rows[$index], $indexes);
        $this->next = 0;
    }

    /**
     * Answers the next row in order.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
