<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves permitted native conversions before a SELECT is published.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class BuiltinCastLowering
{
    /**
     * Resolves the selected conversion and checks its input type and structural role.
     *
     * @throws InvalidConstruction When a type or operand is outside the checked domain
     */
    public function lower(Cast $cast, SourceBinding|JoinBinding $binding, bool $catalogFirst): BuiltinCast|TextLiteral
    {
        $type = $binding->typeOf($cast);
        Check::input(!$cast->type->setOf && $type instanceof Known, 'The cast must resolve to a scalar pg_catalog target.');
        $operand = $cast->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($type->descriptor === Builtin::Text && $operand instanceof Constant && $operand->value instanceof StringConstant) {
            return new TextLiteral($operand->value->value);
        }
        $target = match ($type->descriptor) {
            Builtin::Int4 => CastDestination::Integer,
            Builtin::Int8 => CastDestination::WideInteger,
            Builtin::Bool => CastDestination::Boolean,
            Builtin::Text => CastDestination::Text,
            default => throw new InvalidConstruction('The cast target must be pg_catalog.int4, pg_catalog.int8, pg_catalog.bool or pg_catalog.text.'),
        };
        $boolean = $target === CastDestination::Boolean ? (new BooleanInput())->untyped($operand) : null;
        if ($boolean !== null) {
            return new BuiltinCast($target, $boolean);
        }
        if ($operand instanceof ParsedNull) {
            return new BuiltinCast($target, new NullLiteral());
        }
        return new BuiltinCast($target, $this->operand($operand, $binding, $catalogFirst));
    }

    /**
     * Resolves a typed source operand without changing its native kind.
     *
     * @throws InvalidConstruction When an operand is outside the checked cast domain
     */
    public function operand(
        Scalar $operand,
        SourceBinding|JoinBinding $binding,
        bool $catalogFirst,
    ): BooleanLiteral|BuiltinCast|ColumnReference|IntegerLiteral|WideIntegerLiteral|TextLiteral|BooleanColumnReference|TextColumnReference|WideIntegerColumnReference {
        $input = $binding->typeOf($operand);
        Check::input($input instanceof Known && in_array($input->descriptor, [Builtin::Int4, Builtin::Int8, Builtin::Bool, Builtin::Text], true), 'The cast input must resolve to pg_catalog.int4, pg_catalog.int8, pg_catalog.bool, pg_catalog.text or NULL.');
        if ($operand instanceof ParsedColumn) {
            return $binding->field($operand);
        }
        $value = (new ExpressionLowering())->lower($operand, $binding, $catalogFirst);
        Check::input($value instanceof BooleanLiteral || $value instanceof ColumnReference || $value instanceof IntegerLiteral || $value instanceof WideIntegerLiteral || $value instanceof TextLiteral || $value instanceof BuiltinCast, 'The cast input has no checked scalar role.');

        return $value;
    }
}
