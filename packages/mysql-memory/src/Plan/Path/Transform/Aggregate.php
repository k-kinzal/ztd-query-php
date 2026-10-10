<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Groups the rows of its input and computes the aggregates of each group.
 *
 * Each output row is the first row of a group followed by the value of each aggregate. Without
 * grouping expressions the whole input is one group, which exists even when the input is empty.
 * With ROLLUP, a super-aggregate row follows the last group of each run of groups that agree on
 * the leading grouping values, from the last grouping expression to the first; its rolled-up
 * grouping values are NULL. A row with ROLLUP also holds the value of each grouping expression,
 * NULL where it is rolled up, and the number of grouping expressions rolled up in it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
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
     * @param list<int|null> $rollupColumns The input position each grouping expression reads when it is a column of the block, set to NULL in the super-aggregate rows that roll it up
     * @param bool $sorted Whether the groups come in the order of their grouping values, as when the server groups by sorting or reads an index; else in the order they first appear, as when it groups in a temporary table
     */
    public function __construct(
        public readonly AccessPath $input,
        public readonly array $groups,
        public readonly array $aggregates,
        public readonly bool $rollup = false,
        public readonly array $rollupColumns = [],
        public readonly bool $sorted = true,
    ) {
    }

    /**
     * Answers the width of the input and one value per aggregate, and with ROLLUP one value per grouping expression and the number rolled up.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width() + count($this->aggregates) + ($this->rollup ? count($this->groups) + 1 : 0);
    }
}
