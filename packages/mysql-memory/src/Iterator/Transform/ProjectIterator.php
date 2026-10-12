<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Project;
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
     * @var list<int|float|string|null> The last computed values retained by a marked row
     */
    private array $retained = [];

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
        $this->retained = [];
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
        if ($this->path->publish) {
            $this->frame->projected = [];
        }
        if ($this->path->retainAt !== null && ($row[$this->path->retainAt] ?? 0) > 0) {
            if ($this->path->publish) {
                $this->frame->projected = $this->retained;
            }

            return $this->path->append ? [...$row, ...$this->retained] : $this->retained;
        }
        $values = $this->path->append ? $row : [];
        foreach ($this->path->expressions as $expression) {
            $value = $expression->evaluate($this->frame);
            $values[] = $value;
            if ($this->path->publish) {
                $this->frame->projected[] = $value;
            }
        }

        if ($this->path->retainAt !== null) {
            $this->retained = array_slice($values, $this->path->append ? count($row) : 0);
        }

        return $values;
    }
}
