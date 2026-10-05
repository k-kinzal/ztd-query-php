<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the result type of the arithmetic and bit operators.
 *
 * Rule: MYSQL-NUMERIC-RESULT-001. The operands are classified by
 * MYSQL-NUMERIC-CONTEXT-001. For `+`, `-` and `*` the result is DOUBLE when
 * an operand is double precision, otherwise DECIMAL when an operand is
 * exact decimal, otherwise a BIGINT that is UNSIGNED when an operand is;
 * an integer subtraction with an unsigned operand is signed under the
 * session mode NO_UNSIGNED_SUBTRACTION and unsigned otherwise; the profile
 * does not hold the mode, so its type is the known choice of the signed and
 * the unsigned BIGINT. `/` yields DOUBLE when an operand
 * is double precision and DECIMAL otherwise. DIV yields a BIGINT, UNSIGNED
 * when an operand is. `%` follows `+` but its integer result takes the
 * sign of the dividend. The bit operators and `~` yield BIGINT UNSIGNED;
 * from MySQL 8.0 they yield VARBINARY when their operands (for the shifts
 * and `~`, the shifted operand) are binary strings other than hexadecimal,
 * bit and NULL literals. Unary minus keeps DECIMAL and DOUBLE, makes an
 * integer a signed BIGINT, and makes an integer literal beyond the negated
 * BIGINT range a DECIMAL. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_unsigned_subtraction.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NumericResult
{
    /**
     * Answers the result type of a binary operator over two operands and their facts.
     */
    public function binary(ArithmeticOperator $operator, Scalar $left, ScalarFact $leftFact, Scalar $right, ScalarFact $rightFact, GrammarRelease $release): TypeFact
    {
        $alternatives = new Alternatives();
        $blocking = $alternatives->blocking([$leftFact->type, $rightFact->type]);
        if ($blocking !== null) {
            return $blocking;
        }
        if ($operator->bitwise()) {
            $operands = $operator === ArithmeticOperator::ShiftLeft || $operator === ArithmeticOperator::ShiftRight ? [[$left, $leftFact]] : [[$left, $leftFact], [$right, $rightFact]];

            return $this->bits($operands, $release);
        }
        $context = new NumericContext();
        $results = [];
        foreach ($alternatives->of($leftFact->type) as $leftType) {
            foreach ($alternatives->of($rightFact->type) as $rightType) {
                $result = $this->arithmetic($operator, $context->classify($left, $leftType), $context->classify($right, $rightType));
                array_push($results, ...($result === null ? [$this->integer(true), $this->integer(false)] : [$result]));
            }
        }

        return $alternatives->known($results);
    }

    /**
     * Answers the result type of an arithmetic operator over two classes, or null when it depends on NO_UNSIGNED_SUBTRACTION.
     */
    public function arithmetic(ArithmeticOperator $operator, NumericClass $left, NumericClass $right): ?TypeDescriptor
    {
        $classes = [$left, $right];
        if (in_array(NumericClass::Double, $classes, true)) {
            return $operator === ArithmeticOperator::IntegerDivide ? $this->integer($left === NumericClass::Unsigned || $right === NumericClass::Unsigned) : new Floating(FloatingKind::Double);
        }
        if ($operator === ArithmeticOperator::IntegerDivide) {
            return $this->integer($left === NumericClass::Unsigned || $right === NumericClass::Unsigned);
        }
        if ($operator === ArithmeticOperator::Divide || in_array(NumericClass::Decimal, $classes, true)) {
            return new Decimal();
        }
        if ($operator === ArithmeticOperator::Modulo) {
            return $this->integer($left === NumericClass::Unsigned);
        }
        $unsigned = in_array(NumericClass::Unsigned, $classes, true);

        return $operator === ArithmeticOperator::Minus && $unsigned ? null : $this->integer($unsigned);
    }

    /**
     * Answers the result type of a bit operator over the operands that decide between integer and binary string arithmetic.
     *
     * @param list<array{Scalar, ScalarFact}> $operands
     */
    public function bits(array $operands, GrammarRelease $release): TypeFact
    {
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            return new Known($this->integer(true));
        }
        $binary = true;
        foreach ($operands as [$expression, $fact]) {
            while ($expression instanceof Grouped) {
                $expression = $expression->operand;
            }
            $binary = $binary && !$expression instanceof RadixLiteral && !$expression instanceof NullLiteral
                && $fact->type instanceof Known && $fact->type->descriptor instanceof Binary;
        }

        return new Known($binary ? new Binary(BinaryKind::VarBinary) : $this->integer(true));
    }

    /**
     * Answers the result type of unary minus over an operand and its facts.
     */
    public function negation(Scalar $operand, ScalarFact $fact): TypeFact
    {
        $alternatives = new Alternatives();
        $blocking = $alternatives->blocking([$fact->type]);
        if ($blocking !== null) {
            return $blocking;
        }
        $results = [];
        foreach ($alternatives->of($fact->type) as $type) {
            $results[] = match ((new NumericContext())->classify($operand, $type)) {
                NumericClass::Signed, NumericClass::Unsigned => $this->beyond($operand) ? new Decimal() : $this->integer(false),
                NumericClass::Decimal => new Decimal(),
                NumericClass::Double => new Floating(FloatingKind::Double),
            };
        }

        return $alternatives->known($results);
    }

    /**
     * Tells whether an operand is an integer literal whose negation lies below the BIGINT range.
     */
    public function beyond(Scalar $operand): bool
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if (!$operand instanceof NumberLiteral || !$operand->beyondSigned()) {
            return false;
        }
        $digits = ltrim($operand->text, '0');

        return strlen($digits) > 19 || strcmp($digits, '9223372036854775808') > 0;
    }

    /**
     * Answers the BIGINT type, unsigned or not.
     */
    public function integer(bool $unsigned): Integral
    {
        return new Integral(IntegralKind::BigInt, null, $unsigned ? [NumericModifier::Unsigned] : []);
    }
}
