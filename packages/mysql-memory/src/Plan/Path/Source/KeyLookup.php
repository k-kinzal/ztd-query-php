<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Source;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Reads at most one row through equality on a complete primary key.
 *
 * The underlying scan identity is retained for locking and transaction visibility. No cost
 * estimate is needed: the declared key establishes the maximum cardinality.
 *
 * @visibility MySqlMemory
 */
final class KeyLookup implements AccessPath
{
    /**
     * @param TableScan $input The table occurrence
     * @param list<array{int, Evaluable, Comparator}> $bindings Column positions, constant values and comparisons
     */
    public function __construct(public readonly TableScan $input, public readonly array $bindings)
    {
    }

    /**
     * Answers the width of the table, including invisible columns.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
