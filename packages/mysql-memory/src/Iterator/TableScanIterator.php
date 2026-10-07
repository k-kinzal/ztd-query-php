<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\TableScan;
use MySqlMemory\Storage\ClusterOrder;

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
     * @param TableScan $path The scan executed
     */
    public function __construct(public readonly TableScan $path)
    {
    }

    /**
     * Takes the rows of the table in order.
     */
    #[\Override]
    public function init(Frame $frame): void
    {
        $this->rows = array_values((new ClusterOrder())->rows($this->path->table));
        $this->next = 0;
    }

    /**
     * Answers the next row.
     */
    #[\Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
