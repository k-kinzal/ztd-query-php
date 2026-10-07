<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Materialize;

/**
 * Reads the rows of a query as a table, computing them when the scan starts.
 *
 * The query is evaluated in a frame of its own: one inside the frame of the joined row for a
 * LATERAL query, else one inside the frame enclosing the block.
 *
 * @visibility MySqlMemory
 */
final class MaterializeIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param Materialize $path The path executed
     * @param RowIterator $query The iterator of the query
     */
    public function __construct(public readonly Materialize $path, public readonly RowIterator $query)
    {
    }

    /**
     * Computes the rows of the query.
     */
    #[\Override]
    public function init(Frame $frame): void
    {
        $inner = new Frame($frame->context, [], $this->path->lateral ? new Frame($frame->context, $frame->row, $frame->outer) : $frame->outer);
        $this->query->init($inner);
        $this->rows = [];
        $width = count($this->path->query->domains);
        while (($row = $this->query->read()) !== null) {
            $this->rows[] = count($row) === $width ? $row : array_slice($row, 0, $width);
        }
        $this->next = 0;
    }

    /**
     * Answers the next row of the query.
     */
    #[\Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
