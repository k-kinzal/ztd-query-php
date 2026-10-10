<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

/**
 * The row an expression of one query block reads, and the frames of the blocks around it.
 *
 * A column of the block is read from the row by its position; a column of an enclosing block
 * (an outer reference) is read from the frame that many blocks out.
 *
 * @visibility MySqlMemory
 */
final class Frame
{
    /**
     * @param Context $context The statement being evaluated
     * @param list<int|float|string|null> $row The current row of the block
     * @param Frame|null $outer The frame of the enclosing block, for a subquery
     */
    public function __construct(public readonly Context $context, public array $row = [], public readonly ?Frame $outer = null)
    {
    }

    /**
     * Answers the frame a number of blocks out; zero is this frame.
     */
    public function out(int $depth): self
    {
        $frame = $this;
        for ($i = 0; $i < $depth && $frame->outer !== null; $i++) {
            $frame = $frame->outer;
        }

        return $frame;
    }

    /**
     * Answers a frame for a subquery of this block, with an empty row.
     */
    public function inner(): self
    {
        return new self($this->context, [], $this);
    }
}
