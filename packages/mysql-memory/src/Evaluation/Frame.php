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
     * Input rows counted by a bound applied before projection for SQL_CALC_FOUND_ROWS.
     */
    public ?int $foundBeforeProjection = null;

    /**
     * The latest row held by a buffered query result, distinct from the projection's input row.
     *
     * @var list<int|float|string|null>
     */
    public array $buffered = [];

    /**
     * The latest fields of each table occurrence read in this frame, keyed by its access path.
     *
     * @var array<int, array{\MySqlMemory\Plan\Path\Source\TableScan, list<int|float|string|null>}>
     */
    public array $scanned = [];

    /**
     * Last computed window slots, retained across an empty dependent execution.
     *
     * @var array<int, int|float|string|null>
     */
    public array $windowSlots = [];

    /**
     * Aggregate folds started by reading an otherwise unread window output.
     * A fresh dependent execution starts new folds; an independent cached frame keeps them.
     *
     * @var array<int, Aggregate\Accumulator>
     */
    public array $unreadWindows = [];

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
