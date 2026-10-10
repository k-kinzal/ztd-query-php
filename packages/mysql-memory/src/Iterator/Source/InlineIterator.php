<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Source\Inline;
use Override;

/**
 * Evaluates the rows of a VALUES list.
 *
 * @visibility MySqlMemory
 */
final class InlineIterator implements RowIterator
{
    private Frame $frame;

    private int $next = 0;

    /**
     * @param Inline $path The path executed
     */
    public function __construct(public readonly Inline $path)
    {
    }

    /**
     * Restarts at the first row.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->next = 0;
    }

    /**
     * Evaluates the next row.
     */
    #[Override]
    public function read(): ?array
    {
        $expressions = $this->path->rows[$this->next++] ?? null;
        if ($expressions === null) {
            return null;
        }
        $row = [];
        foreach ($expressions as $expression) {
            $row[] = $expression->evaluate($this->frame);
        }

        return $row;
    }
}
