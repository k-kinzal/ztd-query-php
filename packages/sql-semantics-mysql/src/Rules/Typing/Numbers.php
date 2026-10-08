<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Resolves the types of arithmetic.
 *
 * An operand takes part as an integer, a decimal or a double: a temporal value with fractional
 * seconds is a decimal and one without is an integer, a string is a double. Integer arithmetic
 * yields a BIGINT as wide as its operands allow; when an operand is a decimal or the operator
 * divides, the result is a DECIMAL whose precision and scale follow from the operands, a
 * division adding div_precision_increment digits to the scale of its dividend; any double makes
 * the result a DOUBLE. An integer product has the digits of both operands, at most 65.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/precision-math-expressions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Numbers
{
    /**
     * @param int $divPrecisionIncrement The digits a division adds to the scale of its dividend
     * @param bool $unsignedSubtraction Whether a subtraction with an unsigned operand is unsigned
     */
    public function __construct(public readonly int $divPrecisionIncrement = 4, public readonly bool $unsignedSubtraction = true)
    {
    }

    /**
     * Answers how an operand takes part in arithmetic: as an integer, a decimal or a double.
     */
    public function operand(Domain $domain): Kind
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Kind::Integer,
            Kind::Decimal => Kind::Decimal,
            Kind::Date, Kind::Time, Kind::DateTime => $domain->decimals > 0 ? Kind::Decimal : Kind::Integer,
            Kind::Double, Kind::String, Kind::Json, Kind::Null => Kind::Double,
        };
    }

    /**
     * Answers the precision and scale an operand brings to decimal arithmetic.
     *
     * @return array{int, int}
     */
    public function digits(Domain $domain): array
    {
        return match ($domain->kind) {
            Kind::Decimal => [$domain->precision(), $domain->decimals],
            Kind::Date => [8, 0],
            Kind::Time => [6 + $domain->decimals, $domain->decimals],
            Kind::DateTime => [14 + $domain->decimals, $domain->decimals],
            Kind::Integer, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => [max(1, $domain->length - ($domain->unsigned ? 0 : 1)), 0],
        };
    }

    /**
     * Resolves the result of an arithmetic operator other than a bit operator.
     */
    public function binary(ArithmeticOperator $operator, Domain $left, Domain $right): Domain
    {
        $kinds = [$this->operand($left), $this->operand($right)];
        if ($operator === ArithmeticOperator::IntegerDivide) {
            $fraction = $left->decimals > 0 && $left->decimals < Domain::NOT_FIXED ? $left->decimals + 1 : 0;

            return Domain::integer(Field::LongLong, max(1, $left->length - $fraction + ($kinds[1] !== Kind::Integer ? 1 : 0)), $left->unsigned || $right->unsigned);
        }
        if (in_array(Kind::Double, $kinds, true)) {
            $null = $left->kind === Kind::Null || $right->kind === Kind::Null;

            return Domain::double($null ? 2 : 23, $null ? 0 : Domain::NOT_FIXED);
        }
        if ($operator === ArithmeticOperator::Divide || in_array(Kind::Decimal, $kinds, true)) {
            return $this->decimal($operator, $this->digits($left), $this->digits($right));
        }
        $unsigned = ($left->unsigned || $right->unsigned) && ($operator !== ArithmeticOperator::Minus || $this->unsignedSubtraction);
        $length = match ($operator) {
            ArithmeticOperator::Multiply => min(65, $this->digits($left)[0] + $this->digits($right)[0]) + ($unsigned ? 0 : 1),
            ArithmeticOperator::Modulo => max($left->length, $right->length) + ($unsigned ? 1 : 0),
            ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::BitXor,
            ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight => max($left->length, $right->length) + 1,
        };

        return Domain::integer(Field::LongLong, min(66, $length), $unsigned);
    }

    /**
     * Resolves decimal arithmetic from the precision and scale of each operand.
     *
     * @param array{int, int} $left
     * @param array{int, int} $right
     */
    public function decimal(ArithmeticOperator $operator, array $left, array $right): Domain
    {
        [$leftPrecision, $leftScale] = $left;
        [$rightPrecision, $rightScale] = $right;
        $scale = max($leftScale, $rightScale);
        $integral = max($leftPrecision - $leftScale, $rightPrecision - $rightScale);
        [$precision, $scale] = match ($operator) {
            ArithmeticOperator::Multiply => [$leftPrecision + $rightPrecision, min(30, $leftScale + $rightScale)],
            ArithmeticOperator::Divide => [$leftPrecision - $leftScale + $rightScale + min(30, $leftScale + $this->divPrecisionIncrement), min(30, $leftScale + $this->divPrecisionIncrement)],
            ArithmeticOperator::Modulo => [$integral + $scale, $scale],
            ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::IntegerDivide,
            ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::BitXor, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight => [$integral + 1 + $scale, $scale],
        };

        return Domain::decimal(min(65, $precision), $scale);
    }

    /**
     * Resolves the negation of an operand: a DECIMAL of its digits for an integer constant that is negative read as a signed BIGINT.
     *
     * @param bool $negative Whether the operand is an integer constant that is negative read as a signed BIGINT
     */
    public function negated(Domain $domain, bool $negative = false): Domain
    {
        return match ($this->operand($domain)) {
            Kind::Integer => $negative && $domain->kind === Kind::Integer ? Domain::decimal(...$this->digits($domain)) : Domain::integer(Field::LongLong, $domain->length + ($domain->unsigned ? 1 : 0)),
            Kind::Decimal => $domain->kind === Kind::Decimal ? $domain : Domain::decimal(...$this->digits($domain)),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::double(23, $domain->kind === Kind::Double ? $domain->decimals : Domain::NOT_FIXED),
        };
    }

    /**
     * Resolves a bit operator on numbers: an unsigned BIGINT.
     */
    public function bits(): Domain
    {
        return Domain::integer(Field::LongLong, 21, true);
    }

    /**
     * Answers how an operand takes part in arithmetic: a hexadecimal or bit literal without an introducer as the unsigned integer its bytes spell.
     *
     * The integer is as wide as the largest value its bytes hold, and a bit literal one digit wider.
     */
    public function numeric(Scalar $node, Domain $domain): Domain
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if (!$node instanceof RadixLiteral || $node->introducer !== null || $domain->kind !== Kind::String) {
            return $domain;
        }
        $digits = strlen(rtrim(sprintf('%.0F', 256 ** $domain->length - 1), '.'));

        return Domain::integer(Field::LongLong, min(20, $digits) + ($node->radix === Radix::Bit ? 1 : 0), true);
    }

    /**
     * Resolves a bit operator on binary strings: a binary string as long as its longest operand, or its left operand for a shift.
     *
     * @param ArithmeticOperator|null $operator The binary operator, or null for the unary inversion
     * @param Domain $left The left operand, or the only one of the inversion
     * @param Domain|null $right The right operand of a binary operator
     */
    public function binaryBits(?ArithmeticOperator $operator, Domain $left, ?Domain $right = null): Domain
    {
        $shift = $operator === ArithmeticOperator::ShiftLeft || $operator === ArithmeticOperator::ShiftRight;
        $length = $shift || $right === null ? $left->length : max($left->length, $right->length);

        return Domain::string($length, Collation::binary());
    }

    /**
     * Resolves a truth value: comparisons, predicates and logical operators.
     */
    public function truth(): Domain
    {
        return Domain::integer(Field::LongLong, 1);
    }
}
