<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Preparation;

use SqlSemantics\Platform\MySql\Rules\Typing\Constants;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Statement\Scalar;

/**
 * Recognizes integer truths that disappear from a tableless query's filter.
 * Unknown values remain dependencies; this rule does not evaluate session state.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConstantTruth
{
    /**
     * Answers an integer constant's truth, including comparisons of known integers.
     */
    public function of(Scalar $condition): ?bool
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        $constants = new Constants();
        if (!$condition instanceof Comparison) {
            $value = $constants->value($condition);
            return $value === null ? null : $value[0] !== 0;
        }
        $left = $constants->value($condition->left);
        $right = $constants->value($condition->right);
        if ($left === null || $right === null) {
            return null;
        }
        $high = ($left[1] && $left[0] < 0) <=> ($right[1] && $right[0] < 0);
        $order = $high === 0 ? $left[0] <=> $right[0] : $high;
        return match ($condition->operator) {
            ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual => $order === 0,
            ComparisonOperator::NotEqual => $order !== 0,
            ComparisonOperator::Less => $order < 0,
            ComparisonOperator::LessOrEqual => $order <= 0,
            ComparisonOperator::Greater => $order > 0,
            ComparisonOperator::GreaterOrEqual => $order >= 0,
        };
    }
}
