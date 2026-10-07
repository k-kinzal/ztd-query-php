<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Transform\Filter;
use Override;

/**
 * Passes the rows of its input for which the condition is true.
 *
 * @visibility MySqlMemory
 */
final class FilterIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @param Filter $path The filter executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Filter $path, public readonly RowIterator $input)
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
     * Answers the next row the condition holds for.
     */
    #[Override]
    public function read(): ?array
    {
        $condition = $this->path->condition;
        while (($row = $this->input->read()) !== null) {
            $this->frame->row = $row;
            if (Convert::toBool($condition->evaluate($this->frame), $condition->domain(), $this->frame->context) === true) {
                return $row;
            }
        }

        return null;
    }
}
