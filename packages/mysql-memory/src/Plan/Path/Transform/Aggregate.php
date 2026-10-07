<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use Override;

/**
 * Groups the rows of its input and computes the aggregates of each group.
 *
 * Each output row is the first row of a group followed by the value of each aggregate. Without
 * grouping expressions the whole input is one group, which exists even when the input is empty.
 * With ROLLUP, super-aggregate rows follow each group, with NULL in the rolled-up grouping columns.
 *
 * @visibility MySqlMemory
 */
final class Aggregate implements AccessPath
{
    /**
     * @param AccessPath $input The rows grouped
     * @param list<Evaluable> $groups The grouping expressions, evaluated over each input row
     * @param list<Accumulation> $aggregates The aggregates computed for each group
     * @param bool $rollup Whether super-aggregate rows are added
     * @param list<int> $rollupColumns The input positions the grouping expressions read, set to NULL in super-aggregate rows
     */
    public function __construct(
        public readonly AccessPath $input,
        public readonly array $groups,
        public readonly array $aggregates,
        public readonly bool $rollup = false,
        public readonly array $rollupColumns = [],
    ) {
    }

    /**
     * Answers the width of the input and one value per aggregate.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width() + count($this->aggregates);
    }
}
