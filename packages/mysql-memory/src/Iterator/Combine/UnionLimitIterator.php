<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Combine;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Combine\UnionLimit;
use Override;

/**
 * Applies streamed UNION bounds before projection and retains separate row counters.
 * The final operand selects emitted-row or table-input counting on MySQL 5.7.
 *
 * @visibility MySqlMemory
 */
final class UnionLimitIterator implements RowIterator
{
    private Frame $frame;
    private int $seen = 0;
    private int $emitted = 0;
    private int $tableRows = 0;
    private bool $lastTable = false;

    /**
     * @param UnionLimit $path The global bounds
     * @param RowIterator $input The operand stream
     */
    public function __construct(public readonly UnionLimit $path, public readonly RowIterator $input)
    {
    }

    /**
     * Starts a fresh input and statement row count.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->seen = 0;
        $this->emitted = 0;
        $this->tableRows = 0;
        $this->lastTable = (new ProjectionRead())->lastTable($this->input);
        $frame->foundBeforeProjection = 0;
        $this->input->init($frame);
    }

    /**
     * Reads visible rows while discarding input outside the global bounds.
     */
    #[Override]
    public function read(): ?array
    {
        $reads = new ProjectionRead();
        $end = $this->path->count === null ? null : ($this->path->count > PHP_INT_MAX - $this->path->offset ? PHP_INT_MAX : $this->path->count + $this->path->offset);
        while ($this->path->calculate || $end === null || $this->seen < $end) {
            $emit = $this->seen >= $this->path->offset && ($end === null || $this->seen < $end);
            $row = $reads->read($this->input, $emit);
            if ($row === null) {
                return null;
            }
            $this->seen++;
            $this->emitted += (int) $emit;
            $this->tableRows += (int) $reads->counted($this->input);
            $this->frame->foundBeforeProjection = $this->lastTable ? ($this->path->calculate ? $this->tableRows : $this->seen) : $this->emitted;
            if ($emit) {
                return $row;
            }
        }

        return null;
    }
}
