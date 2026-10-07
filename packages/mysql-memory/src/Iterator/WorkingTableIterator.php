<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\WorkingTable;
use Override;

/**
 * Reads the rows of the last iteration of a recursive common table expression.
 *
 * @visibility MySqlMemory
 */
final class WorkingTableIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param WorkingTable $path The working table
     */
    public function __construct(public readonly WorkingTable $path)
    {
    }

    /**
     * Takes the rows of the last iteration.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->rows = $this->path->rows;
        $this->next = 0;
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
