<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Combine;

use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use Override;

/**
 * Pairs the rows of two inputs, reading the inner input again for each row of the outer one.
 *
 * For a right join the right input is the outer one; the paired row is always the left columns
 * followed by the right columns.
 *
 * @visibility MySqlMemory
 */
final class NestedLoopJoinIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @var list<int|float|string|null>|null
     */
    private ?array $outerRow = null;

    private bool $matched = false;

    /**
     * @param NestedLoopJoin $path The join executed
     * @param RowIterator $left The iterator of the left input
     * @param RowIterator $right The iterator of the right input
     */
    public function __construct(public readonly NestedLoopJoin $path, public readonly RowIterator $left, public readonly RowIterator $right)
    {
    }

    /**
     * Starts the outer input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->outerRow = null;
        $this->outer()->init($frame);
    }

    /**
     * Answers the input read once: the right one for a right join, else the left one.
     */
    public function outer(): RowIterator
    {
        return $this->path->kind === JoinKind::Right ? $this->right : $this->left;
    }

    /**
     * Answers the input read for each outer row.
     */
    public function inner(): RowIterator
    {
        return $this->path->kind === JoinKind::Right ? $this->left : $this->right;
    }

    /**
     * Answers the next paired row.
     */
    #[Override]
    public function read(): ?array
    {
        $right = $this->path->kind === JoinKind::Right;
        while (true) {
            if ($this->outerRow === null) {
                $this->outerRow = $this->outer()->read();
                if ($this->outerRow === null) {
                    return null;
                }
                $this->matched = false;
                $this->frame->row = $right ? array_merge(array_fill(0, $this->path->left->width(), null), $this->outerRow) : $this->outerRow;
                $this->inner()->init($this->frame);
            }
            $innerRow = $this->inner()->read();
            if ($innerRow === null) {
                $outerRow = $this->outerRow;
                $this->outerRow = null;
                if (!$this->matched && $this->path->kind !== JoinKind::Inner) {
                    $nulls = array_fill(0, $right ? $this->path->left->width() : $this->path->right->width(), null);

                    return $right ? array_merge($nulls, $outerRow) : array_merge($outerRow, $nulls);
                }
                continue;
            }
            $row = $right ? array_merge($innerRow, $this->outerRow) : array_merge($this->outerRow, $innerRow);
            if ($this->path->condition !== null) {
                $this->frame->row = $row;
                if (Convert::toBool($this->path->condition->evaluate($this->frame), $this->path->condition->domain(), $this->frame->context) !== true) {
                    continue;
                }
            }
            $this->matched = true;

            return $row;
        }
    }
}
