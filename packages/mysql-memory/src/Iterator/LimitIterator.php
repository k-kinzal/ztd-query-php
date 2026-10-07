<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Limit;

/**
 * Skips the offset rows of its input and passes at most the count of the rest.
 *
 * @visibility MySqlMemory
 */
final class LimitIterator implements RowIterator
{
    private int $seen = 0;

    /**
     * @param Limit $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Limit $path, public readonly RowIterator $input)
    {
    }

    /**
     * Starts the input.
     */
    #[\Override]
    public function init(Frame $frame): void
    {
        $this->seen = 0;
        $this->input->init($frame);
    }

    /**
     * Answers the next row within the bounds.
     */
    #[\Override]
    public function read(): ?array
    {
        $end = $this->path->count === null ? null : $this->path->offset + $this->path->count;
        while ($end === null || $this->seen < $end) {
            $row = $this->input->read();
            if ($row === null) {
                return null;
            }
            if ($this->seen++ >= $this->path->offset) {
                return $row;
            }
        }

        return null;
    }
}
