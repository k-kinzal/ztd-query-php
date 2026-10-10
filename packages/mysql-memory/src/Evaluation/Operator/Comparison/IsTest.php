<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Json\Coercions;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\DoubleOperand;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Operator\Negation;
use MySqlMemory\Evaluation\Subquery\ScalarRead;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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
     * NOT LIKE, or the negation of a LIKE, reads and checks the escape of the LIKE first, as the
     * server evaluates the LIKE it negates.
     */
    public static function absent(Evaluable $value, Frame $frame): bool
    {
        if ($value instanceof Retyped) {
            return self::absent($value->evaluable, $frame);
        }
        if ($value instanceof DoubleOperand) {
            return self::absent($value->operand, $frame);
        }
        if ($value instanceof Compare && $value->operator === ComparisonOperator::NullSafeEqual) {
            return false;
        }
        if ($value instanceof Compare && $value->nullFromOperands) {
            return self::absent($value->left, $frame) || self::absent($value->right, $frame);
        }
        if ($value instanceof Pattern) {
            if ($value->negated && $value->escape !== null) {
                $value->escapeText($frame);
            }

            return self::absent($value->operand, $frame) || self::absent($value->pattern, $frame);
        }
        if ($value instanceof Negation) {
            $negated = $value->operand;
            while ($negated instanceof Retyped) {
                $negated = $negated->evaluable;
            }
            if ($negated instanceof Pattern && $negated->escape !== null) {
                $negated->escapeText($frame);
            }

            return self::absent($value->operand, $frame);
        }
        if ($value instanceof Logic && $value->operator === LogicalOperator::Xor) {
            return self::absent($value->left, $frame) || self::absent($value->right, $frame);
        }
        if ($value instanceof ScalarRead && $value->domain()->kind === Kind::Json && $frame->context->modes->release === GrammarRelease::MySql5744) {
            return self::legacyJsonNull($value, $frame);
        }

        return $value->evaluate($frame) === null;
    }

    /**
     * Tests a MySQL 5.7 JSON scalar result through its integer conversion.
     *
     * Direct JSON expressions do not perform this conversion. Scalar subqueries
     * do, with an unknown column name and the consumed aggregate input position.
     * Verified by differential SQL on MySQL 5.7.44.
     */
    public static function legacyJsonNull(ScalarRead $value, Frame $frame): bool
    {
        $stored = $value->evaluate($frame);
        $row = $frame->context->row;
        $frame->context->row = $frame->context->aggregateRow;
        try {
            if ($stored !== null) {
                Coercions::toInteger((string) $stored, $value->domain()->withSource('?'), $frame->context);
            }

            return $stored === null;
        } finally {
            $frame->context->row = $row;
        }
    }
}
