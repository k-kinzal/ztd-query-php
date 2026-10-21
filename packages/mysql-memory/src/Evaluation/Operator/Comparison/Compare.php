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
     * @param bool $hashed Whether a string join equality first compares the operands' hash keys
     */
    public function __construct(
        public readonly ComparisonOperator $operator,
        public readonly Evaluable $left,
        public readonly Evaluable $right,
        public readonly Comparator $comparator,
        public readonly Domain $domain,
        public readonly bool $nullFromOperands = false,
        public readonly bool $hashed = false,
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
        $left = $this->operand($this->left, $frame);
        if ($left === null && $this->operator !== ComparisonOperator::NullSafeEqual) {
            return null;
        }
        $right = $this->operand($this->right, $frame);
        if ($this->operator === ComparisonOperator::NullSafeEqual) {
            if ($left === null || $right === null) {
                return $left === null && $right === null ? 1 : 0;
            }
        }
        if ($this->hashed && $right !== null) {
            $ordering = \MySqlMemory\Typing\Ordering::of($this->comparator->collation);
            if ($ordering->key(Comparator::text($left, $this->comparator->left, $this->comparator->collation), true) !== $ordering->key(Comparator::text($right, $this->comparator->right, $this->comparator->collation), true)) {
                return 0;
            }
        }
        $order = $this->comparator->compare($left, $right, $frame->context);
        if ($order === null) {
            return null;
        }

        return self::holds($this->operator, $order) ? 1 : 0;
    }

    /**
     * Reads a comparison operand. Null-safe equality can finish its truth value
     * after a deferred scalar failure before MySQL 9. MySQL 9 and ordinary
     * comparisons propagate it without completing that truth value.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluation cannot continue
     */
    public function operand(Evaluable $operand, Frame $frame): int|float|string|null
    {
        try {
            return $operand->evaluate($frame);
        } catch (\MySqlMemory\Error\SqlError $error) {
            if ($this->operator !== ComparisonOperator::NullSafeEqual || in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true)) {
                throw $error;
            }
            $frame->context->defer($error);

            return null;
        }
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
