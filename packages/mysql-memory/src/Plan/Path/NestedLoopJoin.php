<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Evaluation\Evaluable;

/**
 * Pairs each row of one input with each row of the other, the left columns first.
 *
 * @visibility MySqlMemory
 */
final class NestedLoopJoin implements AccessPath
{
    /**
     * @param AccessPath $left The left input
     * @param AccessPath $right The right input
     * @param JoinKind $kind How rows are paired
     * @param Evaluable|null $condition The join condition over the paired row, or null for every pair
     * @param bool $lateral Whether the right input reads the columns of the left row
     */
    public function __construct(
        public readonly AccessPath $left,
        public readonly AccessPath $right,
        public readonly JoinKind $kind,
        public readonly ?Evaluable $condition,
        public readonly bool $lateral = false,
    ) {
    }

    /**
     * Answers the width of both inputs.
     */
    #[\Override]
    public function width(): int
    {
        return $this->left->width() + $this->right->width();
    }
}
