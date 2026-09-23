<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Resolves temporal result families and inferred parameters without evaluating operands.
 * @visibility SqlSemantics
 */
final class DateArithmeticFacts
{
    /**
     * Supplies a missing dynamic parameter type when the selected release infers one.
     */
    public static function parameter(Expression $input, string $type, DateArithmeticRules $rules): Expression
    {
        if ($rules !== DateArithmeticRules::Current || !$input instanceof Parameter || $input->type->identity !== BuiltinIdentity::Unknown) {
            return $input;
        }
        return $input->withFacts(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, $type), $input->nullability, $input->nullExtendedBy));
    }

    /**
     * Derives the SQL result family from declarations and interval fields.
     */
    public static function result(Expression $value, MySqlUnit $unit, DateArithmeticRules $rules): TypeDescriptor
    {
        $name = $value->type->name;
        $result = match ($name) {
            'datetime', 'timestamp' => 'datetime',
            'date' => IntervalFields::calendarOnly($unit) ? 'date' : 'datetime',
            'time' => $rules === DateArithmeticRules::Legacy || IntervalFields::clockOnly($unit) ? 'time' : 'datetime',
            'unknown' => $value instanceof Literal || $value instanceof Parameter ? 'varchar' : 'unknown',
            default => 'varchar',
        };
        return TypeDescriptor::builtin(Dialect::MySql, $result);
    }

    /**
     * Invalid or out-of-range temporal arithmetic may return NULL even for non-NULL inputs.
     */
    public static function nullability(Expression $value, Expression $quantity): Nullability
    {
        return $value->nullability === Nullability::AlwaysNull || $quantity->nullability === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
    }
}
