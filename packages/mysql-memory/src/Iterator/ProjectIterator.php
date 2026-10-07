<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Project;
use Override;

/**
 * Computes the output values of each row of its input.
 *
 * @visibility MySqlMemory
 */
final class ProjectIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @param Project $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Project $path, public readonly RowIterator $input)
    {
    }

    /**
     * Starts the input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->input->init($frame);
    }

    /**
     * Answers the output values of the next row.
     */
    #[Override]
    public function read(): ?array
    {
        $row = $this->input->read();
        if ($row === null) {
            return null;
        }
        $this->frame->row = $row;
        $values = [];
        foreach ($this->path->expressions as $expression) {
            $values[] = $expression->evaluate($this->frame);
        }

        return $values;
    }
}
