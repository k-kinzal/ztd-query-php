<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Combine;

use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Iterator\Source\SingleRowIterator;
use MySqlMemory\Iterator\Transform\FilterIterator;
use MySqlMemory\Iterator\Transform\PrepareIterator;
use MySqlMemory\Iterator\Transform\ProjectIterator;

/**
 * Reads discarded UNION input without evaluating its visible select expressions.
 *
 * @visibility MySqlMemory
 */
final class ProjectionRead
{
    /**
     * Advances one input, preserving projection only for an emitted row.
     *
     * @return list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>|null
     */
    public function read(RowIterator $iterator, bool $emit): ?array
    {
        if (!$emit && $iterator instanceof ProjectIterator) {
            return $this->read($iterator->input, false);
        }

        return $iterator instanceof UnionStreamIterator ? $iterator->read($emit) : $iterator->read();
    }

    /**
     * Identifies table rows included in the count even when projection is skipped.
     * Constant SELECT operands contribute only when their row is emitted.
     */
    public function counted(RowIterator $iterator): bool
    {
        while ($iterator instanceof ProjectIterator || $iterator instanceof FilterIterator || $iterator instanceof PrepareIterator) {
            $iterator = $iterator->input;
        }

        return $iterator instanceof UnionStreamIterator ? $iterator->counted : !$iterator instanceof SingleRowIterator;
    }

    /**
     * Identifies the final operand's row source before any operand is consumed.
     * MySQL 5.7 reports emitted rows when this operand is a constant SELECT.
     */
    public function lastTable(RowIterator $iterator): bool
    {
        while ($iterator instanceof ProjectIterator || $iterator instanceof FilterIterator || $iterator instanceof PrepareIterator) {
            $iterator = $iterator->input;
        }

        return $iterator instanceof UnionStreamIterator ? $this->lastTable($iterator->right) : !$iterator instanceof SingleRowIterator;
    }
}
