<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Operator\Negation;
use MySqlMemory\Evaluation\Operator\Numeric;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;

/**
 * IS [NOT] NULL, IS [NOT] TRUE, IS [NOT] FALSE and IS [NOT] UNKNOWN: never NULL.
 *
 * IS NULL and IS UNKNOWN of an operand that cannot be NULL do not evaluate it; IS NOT NULL and IS
 * NOT UNKNOWN always do. Of a comparison, LIKE, NOT or XOR they evaluate only the nullness of its
 * operands (see absent()).
 *
 * @visibility MySqlMemory
 */
final class IsTest implements Evaluable
{
    /**
     * @param Evaluable $operand The operand
     * @param bool|null $truth The truth value tested, or null for NULL and UNKNOWN
     * @param bool $negated Whether NOT is written
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly Evaluable $operand, public readonly ?bool $truth, public readonly bool $negated, public readonly Domain $domain)
    {
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
     * Tests the operand for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int
    {
        $holds = match (true) {
            $this->truth === null && $this->negated => self::absent($this->operand, $frame),
            $this->truth === null => self::isNull($this->operand, $frame),
            default => Convert::toBool($this->operand->evaluate($frame), $this->operand->domain(), $frame->context) === $this->truth,
        };

        return $holds !== $this->negated ? 1 : 0;
    }

    /**
     * Tells whether an operand is NULL, as IS NULL and ISNULL() test it.
     *
     * An operand that cannot be NULL is not evaluated at all.
     */
    public static function isNull(Evaluable $operand, Frame $frame): bool
    {
        return $operand->domain()->nullable && self::absent($operand, $frame);
    }

    /**
     * Tells whether a value is NULL, evaluating no more of it than that takes.
     *
     * A comparison, LIKE, NOT and XOR are NULL exactly when an operand is, so only the nullness of
     * their operands is evaluated, from the left, and `<=>` is never NULL; a comparison of a string as a number or a
     * temporal value with an operand that varies by row is evaluated whole. Anything else is evaluated.
     */
    public static function absent(Evaluable $value, Frame $frame): bool
    {
        if ($value instanceof Retyped) {
            return self::absent($value->evaluable, $frame);
        }
        if ($value instanceof Numeric) {
            return self::absent($value->operand, $frame);
        }
        if ($value instanceof Compare && $value->operator === ComparisonOperator::NullSafeEqual) {
            return false;
        }
        if ($value instanceof Compare && $value->nullFromOperands) {
            return self::absent($value->left, $frame) || self::absent($value->right, $frame);
        }
        if ($value instanceof Pattern) {
            return self::absent($value->operand, $frame) || self::absent($value->pattern, $frame);
        }
        if ($value instanceof Negation) {
            return self::absent($value->operand, $frame);
        }
        if ($value instanceof Logic && $value->operator === LogicalOperator::Xor) {
            return self::absent($value->left, $frame) || self::absent($value->right, $frame);
        }

        return $value->evaluate($frame) === null;
    }
}
