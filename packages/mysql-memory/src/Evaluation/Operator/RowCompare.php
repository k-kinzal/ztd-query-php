<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

/**
 * A comparison of two rows, element by element.
 *
 * Rows are equal when every pair of elements is equal; unknown when no pair differs and one is
 * unknown. Order comparisons decide by the first pair that is not equal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-constructor-optimization.html.
 *
 * @visibility MySqlMemory
 */
final class RowCompare implements Evaluable
{
    /**
     * @param ComparisonOperator $operator The comparison
     * @param list<array{Evaluable, Evaluable, Comparator}> $pairs The elements of both rows and how each pair compares
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly ComparisonOperator $operator, public readonly array $pairs, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Compares the rows for a row of the block.
     */
    #[\Override]
    public function evaluate(Frame $frame): ?int
    {
        $unknown = false;
        foreach ($this->pairs as [$left, $right, $comparator]) {
            $one = $left->evaluate($frame);
            $two = $right->evaluate($frame);
            if ($this->operator === ComparisonOperator::NullSafeEqual && ($one === null || $two === null)) {
                if (($one === null) !== ($two === null)) {
                    return 0;
                }
                continue;
            }
            $order = $comparator->compare($one, $two, $frame->context);
            if ($order === null) {
                $unknown = true;
                if ($this->operator !== ComparisonOperator::Equal && $this->operator !== ComparisonOperator::NotEqual) {
                    return null;
                }
                continue;
            }
            if ($order !== 0) {
                return Compare::holds($this->operator, $order) ? 1 : 0;
            }
        }

        return $unknown ? null : (Compare::holds($this->operator, 0) ? 1 : 0);
    }
}
