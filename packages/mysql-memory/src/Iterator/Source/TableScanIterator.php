<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Storage\ClusterOrder;
use Override;

/**
 * Reads the rows of a table in clustered index order, as they were when the scan started.
 *
 * @visibility MySqlMemory
 */
final class TableScanIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @var list<int> The row numbers, in the order of the rows
     */
    private array $numbers = [];

    /**
     * The number of the row read last, or null before the first and after the last.
     */
    public ?int $current = null;

    /**
     * @param TableScan $path The scan executed
     */
    public function __construct(public readonly TableScan $path)
    {
    }

    /**
     * Takes the rows of the table in order.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $rows = (new ClusterOrder())->rows($this->path->table);
        $this->numbers = array_keys($rows);
        $this->rows = array_values($rows);
        $this->next = 0;
        $this->current = null;
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        $this->current = $this->numbers[$this->next] ?? null;

        return $this->rows[$this->next++] ?? null;
    }
}
