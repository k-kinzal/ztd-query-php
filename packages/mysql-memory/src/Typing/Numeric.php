<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Result\FieldType;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;

/**
 * Resolves the domain of arithmetic: how each operand is read, and the domain of the result.
 *
 * An integer, a year, a bit value and a date or time without fractional seconds are read as
 * integers; a decimal and a temporal value with fractional seconds as decimals; everything else
 * (a double, a string, NULL) as a double. The result is a double when an operand is, a decimal
 * when an operand is, else an integer that is unsigned when an operand is.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Numeric
{
    /**
     * Answers the kind an operand of a domain is read as: Integer, Decimal or Double.
     */
    public static function operand(Domain $domain): Kind
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Kind::Integer,
            Kind::Decimal => Kind::Decimal,
            Kind::Date, Kind::Time, Kind::DateTime => $domain->decimals > 0 ? Kind::Decimal : Kind::Integer,
            default => Kind::Double,
        };
    }

    /**
     * Answers the precision and scale an operand has when read as a decimal.
     *
     * @return array{int, int}
     */
    public static function digits(Domain $domain): array
    {
        return match ($domain->kind) {
            Kind::Decimal => [$domain->precision(), $domain->decimals],
            Kind::Date => [8, 0],
            Kind::Time => [6 + $domain->decimals, $domain->decimals],
            Kind::DateTime => [14 + $domain->decimals, $domain->decimals],
            default => [max(1, $domain->length - ($domain->unsigned ? 0 : 1)), 0],
        };
    }

    /**
     * Answers the domain of a binary arithmetic operator over two operand domains.
     */
    public static function binary(ArithmeticOperator $operator, Domain $left, Domain $right, int $divIncrement): Domain
    {
        $nullable = $left->nullable || $right->nullable || in_array($operator, [ArithmeticOperator::Divide, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide], true);
        $kinds = [self::operand($left), self::operand($right)];
        if ($operator === ArithmeticOperator::IntegerDivide) {
            return Domain::integer(FieldType::LongLong, max(1, $left->length - ($left->decimals > 0 && $left->decimals < Domain::NOT_FIXED ? $left->decimals + 1 : 0) + ($kinds[1] !== Kind::Integer ? 1 : 0)), $left->unsigned || $right->unsigned)->withNullable($nullable);
        }
        if (in_array(Kind::Double, $kinds, true)) {
            $decimals = $left->kind === Kind::Null || $right->kind === Kind::Null ? 0 : Domain::NOT_FIXED;

            return new Domain(Kind::Double, FieldType::Double, $decimals === 0 ? 2 : 23, $decimals, false, Collation::Binary, $nullable);
        }
        if ($operator === ArithmeticOperator::Divide || in_array(Kind::Decimal, $kinds, true)) {
            return self::decimal($operator, self::digits($left), self::digits($right), $divIncrement, $left->unsigned && $right->unsigned)->withNullable($nullable);
        }
        $unsigned = $left->unsigned || $right->unsigned;
        $length = match ($operator) {
            ArithmeticOperator::Multiply => $left->length + $right->length - 1,
            ArithmeticOperator::Modulo => max($left->length, $right->length),
            default => max($left->length, $right->length) + 1,
        };

        return Domain::integer(FieldType::LongLong, min(21, $length), $unsigned)->withNullable($nullable);
    }

    /**
     * Answers the decimal domain of an operator over operands of precisions and scales.
     *
     * @param array{int, int} $left
     * @param array{int, int} $right
     */
    public static function decimal(ArithmeticOperator $operator, array $left, array $right, int $divIncrement, bool $unsigned): Domain
    {
        [$leftPrecision, $leftScale] = $left;
        [$rightPrecision, $rightScale] = $right;
        [$precision, $scale] = match ($operator) {
            ArithmeticOperator::Multiply => [$leftPrecision + $rightPrecision, min(30, $leftScale + $rightScale)],
            ArithmeticOperator::Divide => [$leftPrecision - $leftScale + $rightScale + min(30, $leftScale + $divIncrement), min(30, $leftScale + $divIncrement)],
            ArithmeticOperator::Modulo => [max($leftPrecision - $leftScale, $rightPrecision - $rightScale) + max($leftScale, $rightScale), max($leftScale, $rightScale)],
            default => [max($leftPrecision - $leftScale, $rightPrecision - $rightScale) + 1 + max($leftScale, $rightScale), max($leftScale, $rightScale)],
        };

        return Domain::decimal(min(65, $precision), $scale, false);
    }
}
