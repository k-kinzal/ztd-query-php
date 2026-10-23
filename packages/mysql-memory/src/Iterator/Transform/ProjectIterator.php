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
        if ($this->path->publish) {
            $this->frame->projected = array_values(array_replace(array_fill(0, count($this->path->expressions), null), $this->path->initial));
        }
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
        if ($this->path->retainAt !== null && ($row[$this->path->retainAt] ?? 0) > 0) {
            if ($this->path->publish) {
                $this->frame->projected = $this->retained;
            }

            return $this->path->append ? [...$row, ...$this->retained] : $this->retained;
        }
        $offset = $this->path->append ? count($row) : 0;
        $values = [...($this->path->append ? $row : []), ...array_fill(0, count($this->path->expressions), null)];
        foreach ($this->path->order === [] ? array_keys($this->path->expressions) : $this->path->order as $position) {
            $value = $this->path->expressions[$position]->evaluate($this->frame);
            $values[$offset + $position] = $value;
            if ($this->path->publish) {
                $this->frame->projected = array_values(array_replace($this->frame->projected, [$position => $value]));
            }
        }

        if ($this->path->retainAt !== null) {
            $this->retained = array_slice($values, $this->path->append ? count($row) : 0);
        }

        return array_values($values);
    }
}
