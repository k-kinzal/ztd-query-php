<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Filter;
use Override;

/**
 * Passes the rows of its input for which the condition is true.
 *
 * The precondition is evaluated once for the statement, before the first row is read; when it is
 * not true the input is not read.
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
        if (!$this->holds()) {
            return null;
        }
        $condition = $this->path->condition;
        while (($row = $this->input->read()) !== null) {
            $this->frame->row = $row;
            if (Convert::toBool($condition->evaluate($this->frame), $condition->domain(), $this->frame->context) === true) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Tells whether the precondition is true, evaluating it the first time the statement needs it.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the precondition is an error
     */
    public function holds(): bool
    {
        $precondition = $this->path->precondition;
        if ($precondition === null) {
            return true;
        }
        $kept = $this->frame->context->kept;
        if (!isset($kept[$this->path])) {
            $kept[$this->path] = [Convert::toBool($precondition->evaluate($this->frame), $precondition->domain(), $this->frame->context) === true ? 1 : 0];
        }

        return $kept[$this->path][0] === 1;
    }
}
