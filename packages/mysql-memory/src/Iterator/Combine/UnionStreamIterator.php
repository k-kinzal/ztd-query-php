<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Combine;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Combine\UnionStream;
use Override;

/**
 * Reads the second UNION operand only after the first has been consumed.
 *
 * @visibility MySqlMemory
 */
final class UnionStreamIterator implements RowIterator
{
    /**
     * Whether the latest operand row contributes when projection is skipped.
     */
    public bool $counted = false;

    private Frame $frame;

    private bool $second = false;

    /**
     * @param UnionStream $path The executed path
     * @param RowIterator $left The first operand's iterator
     * @param RowIterator $right The second operand's iterator
     */
    public function __construct(public readonly UnionStream $path, public readonly RowIterator $left, public readonly RowIterator $right)
    {
    }

    /**
     * Starts the first operand and resets retained result fields.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->second = false;
        $this->counted = false;
        $frame->buffered = [];
        $this->left->init(new Frame($frame->context, [], $frame->outer));
    }

    /**
     * Returns the next operand row, preserving only the combined output columns.
     */
    #[Override]
    public function read(bool $emit = true): ?array
    {
        $reads = new ProjectionRead();
        $row = $reads->read($this->second ? $this->right : $this->left, $emit);
        if ($row === null && !$this->second) {
            $this->second = true;
            $this->right->init(new Frame($this->frame->context, [], $this->frame->outer));
            $row = $reads->read($this->right, $emit);
        }
        if ($row === null) {
            return null;
        }
        $this->counted = $reads->counted($this->second ? $this->right : $this->left);
        $row = array_slice($row, 0, $this->path->columns);
        if ($emit && $this->path->retain) {
            $this->frame->buffered = $row;
        }

        return $row;
    }
}
