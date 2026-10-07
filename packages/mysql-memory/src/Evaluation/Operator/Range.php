<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * [NOT] BETWEEN: whether a value lies between two bounds, both included.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_between.
 *
 * @visibility MySqlMemory
 */
final class Range implements Evaluable
{
    /**
     * @param Evaluable $operand The value tested
     * @param Evaluable $low The lower bound
     * @param Evaluable $high The upper bound
     * @param Comparator $lowComparator How the value compares with the lower bound
     * @param Comparator $highComparator How the value compares with the upper bound
     * @param bool $negated Whether NOT is written
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(
        public readonly Evaluable $operand,
        public readonly Evaluable $low,
        public readonly Evaluable $high,
        public readonly Comparator $lowComparator,
        public readonly Comparator $highComparator,
        public readonly bool $negated,
        public readonly Domain $domain,
    ) {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Tests the value for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $value = $this->operand->evaluate($frame);
        $low = $this->lowComparator->compare($value, $this->low->evaluate($frame), $frame->context);
        $high = $this->highComparator->compare($value, $this->high->evaluate($frame), $frame->context);
        $above = $low === null ? null : $low >= 0;
        $below = $high === null ? null : $high <= 0;
        if ($above === false || $below === false) {
            return $this->negated ? 1 : 0;
        }
        if ($above === null || $below === null) {
            return null;
        }

        return $this->negated ? 0 : 1;
    }
}
