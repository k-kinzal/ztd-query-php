<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * GROUPING(): one bit per argument, the first argument the most significant, set when the row rolls up the grouping expression the argument names.
 *
 * A row WITH ROLLUP holds the number of grouping expressions it rolls up, which are the last
 * ones; the argument naming the grouping expression at position p is rolled up when p is at
 * least the number of grouping expressions less that number.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_grouping.
 *
 * @visibility MySqlMemory
 */
final class GroupingFlags implements Evaluable
{
    /**
     * @param Domain $domain The domain of the result
     * @param list<int> $arguments The position among the grouping expressions of the one each argument names
     * @param int $groups The number of grouping expressions
     * @param int $level The position in the row of the number of grouping expressions rolled up
     */
    public function __construct(public readonly Domain $domain, public readonly array $arguments, public readonly int $groups, public readonly int $level)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Answers the bits of the arguments for the current row.
     */
    #[Override]
    public function evaluate(Frame $frame): int
    {
        $rolled = (int) ($frame->row[$this->level] ?? 0);
        $flags = 0;
        foreach ($this->arguments as $position) {
            $flags = ($flags << 1) | ($position >= $this->groups - $rolled ? 1 : 0);
        }

        return $flags;
    }
}
