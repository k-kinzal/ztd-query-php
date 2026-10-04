<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Classifies a value by the arithmetic it takes part in when used as a number.
 *
 * Rule: MYSQL-NUMERIC-CONTEXT-001. An integer type is a signed or unsigned
 * integer by its attributes; BOOL is a signed integer; SERIAL and BIT are
 * unsigned integers; DECIMAL is exact. A temporal value is converted to a
 * number: an integer when it has no fractional seconds, otherwise a decimal.
 * A hexadecimal or bit literal without introducer is an unsigned integer
 * in a numeric context. FLOAT, DOUBLE, every string, JSON, spatial and
 * vector value, and a bare NULL take part as double precision.
 * Terminates: a grouping is unwrapped in a loop over a finite chain.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html,
 * https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NumericContext
{
    /**
     * Answers the class of a value of a type, written as an expression; a null type is a bare NULL.
     */
    public function classify(Scalar $expression, ?TypeDescriptor $type): NumericClass
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof RadixLiteral && $expression->introducer === null) {
            return NumericClass::Unsigned;
        }

        return match (true) {
            $type instanceof Integral => $type->unsigned() ? NumericClass::Unsigned : NumericClass::Signed,
            $type instanceof Decimal => NumericClass::Decimal,
            $type instanceof Temporal => $this->temporal($type),
            $type instanceof Elementary => $this->elementary($type),
            $type instanceof Floating => NumericClass::Double,
            default => NumericClass::Double,
        };
    }

    /**
     * Answers the class of a temporal value converted to a number.
     */
    public function temporal(Temporal $type): NumericClass
    {
        if ($type->kind === TemporalKind::Date || $type->kind === TemporalKind::Year) {
            return NumericClass::Signed;
        }

        return $type->precision === null || ltrim($type->precision, '0') === '' ? NumericClass::Signed : NumericClass::Decimal;
    }

    /**
     * Answers the class of BOOL, SERIAL, BIT, JSON and VECTOR values.
     */
    public function elementary(Elementary $type): NumericClass
    {
        return match ($type->kind) {
            ElementaryKind::Boolean => NumericClass::Signed,
            ElementaryKind::Serial, ElementaryKind::Bit => NumericClass::Unsigned,
            ElementaryKind::Json, ElementaryKind::Vector => NumericClass::Double,
        };
    }
}
