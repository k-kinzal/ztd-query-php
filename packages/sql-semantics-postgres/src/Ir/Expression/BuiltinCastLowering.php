<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
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
        if ($type->descriptor === Builtin::Text) {
            Check::input($operand instanceof Constant && $operand->value instanceof StringConstant, 'A text literal cast requires a decoded string constant.');

            return new TextLiteral($operand->value->value);
        }
        $target = match ($type->descriptor) {
            Builtin::Int4 => CastDestination::Integer,
            Builtin::Int8 => CastDestination::WideInteger,
            Builtin::Bool => CastDestination::Boolean,
            default => throw new InvalidConstruction('The cast target must be pg_catalog.int4, pg_catalog.int8, pg_catalog.bool or a text literal.'),
        };
        if ($operand instanceof ParsedNull) {
            return new BuiltinCast($target, new NullLiteral());
        }
        $input = $binding->typeOf($operand);
        Check::input($input instanceof Known && in_array($input->descriptor, [Builtin::Int4, Builtin::Int8, Builtin::Bool], true), 'The cast input must resolve to pg_catalog.int4, pg_catalog.int8, pg_catalog.bool or NULL.');
        $value = (new ExpressionLowering())->lower($operand, $binding, $catalogFirst);
        Check::input($value instanceof BooleanLiteral || $value instanceof ColumnReference || $value instanceof IntegerLiteral || $value instanceof WideIntegerLiteral || $value instanceof BuiltinCast, 'The cast input has no checked scalar role.');

        return new BuiltinCast($target, $value);
    }
}
