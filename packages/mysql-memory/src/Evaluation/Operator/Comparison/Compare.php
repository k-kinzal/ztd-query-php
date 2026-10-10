<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

/**
 * A comparison of two values: 1, 0, or NULL when either is NULL; `<=>` is never NULL.
 *
 * The right operand is not evaluated when the left one is NULL, except for `<=>`. Whether the
 * comparison is NULL can be told from the operands alone, without comparing them, unless a string
 * is compared as a number or a temporal value with an operand that also varies by row.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html.
 *
 * @visibility MySqlMemory
 */
final class Compare implements Evaluable
{
    /**
     * @param ComparisonOperator $operator The operator
     * @param Evaluable $left The left operand
     * @param Evaluable $right The right operand
     * @param Comparator $comparator How the operands compare
     * @param Domain $domain The domain of the truth value
     * @param bool $nullFromOperands Whether IS NULL tells whether the comparison is NULL from the nullness of the operands alone
     */
    public function __construct(
        public readonly ComparisonOperator $operator,
        public readonly Evaluable $left,
        public readonly Evaluable $right,
        public readonly Comparator $comparator,
        public readonly Domain $domain,
        public readonly bool $nullFromOperands = false,
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
     * Compares the operands for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $left = $this->left->evaluate($frame);
        if ($left === null && $this->operator !== ComparisonOperator::NullSafeEqual) {
            return null;
        }
        $right = $this->right->evaluate($frame);
        if ($this->operator === ComparisonOperator::NullSafeEqual) {
            if ($left === null || $right === null) {
                return $left === null && $right === null ? 1 : 0;
            }
        }
        $order = $this->comparator->compare($left, $right, $frame->context);
        if ($order === null) {
            return null;
        }

        return self::holds($this->operator, $order) ? 1 : 0;
    }

    /**
     * Tells whether an operator holds for an order of its operands.
     */
    public static function holds(ComparisonOperator $operator, int $order): bool
    {
        return match ($operator) {
            ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual => $order === 0,
            ComparisonOperator::NotEqual => $order !== 0,
            ComparisonOperator::Less => $order < 0,
            ComparisonOperator::LessOrEqual => $order <= 0,
            ComparisonOperator::Greater => $order > 0,
            ComparisonOperator::GreaterOrEqual => $order >= 0,
        };
    }
}
