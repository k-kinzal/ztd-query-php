<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Limit;
use Override;

/**
 * Skips the offset rows of its input and passes at most the count of the rest.
 *
 * @visibility MySqlMemory
 */
final class LimitIterator implements RowIterator
{
    private int $seen = 0;

    private Frame $frame;

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
    #[Override]
    public function init(Frame $frame): void
    {
        $this->seen = 0;
        $this->frame = $frame;
        if ($this->path->countFound) {
            $frame->foundBeforeProjection = 0;
        }
        if ($this->path->initialize || $this->path->exhaust || $this->path->count !== 0 || $this->path->offset !== 0) {
            $this->input->init($frame);
        }
    }

    /**
     * Answers the next row within the bounds.
     */
    #[Override]
    public function read(): ?array
    {
        $end = $this->path->count === null ? null : $this->path->offset + $this->path->count;
        while ($this->path->exhaust || $end === null || $this->seen < $end) {
            $row = $this->input->read();
            if ($row === null) {
                return null;
            }
            if ($this->path->countFound) {
                $this->frame->foundBeforeProjection = $this->seen + 1;
            }
            $position = $this->seen++;
            if ($position >= $this->path->offset && ($end === null || $position < $end)) {
                return $row;
            }
        }

        return null;
    }
}
