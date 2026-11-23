<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves one pure operand and its type before comparison inference.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IntegerAtomLowering
{
    /**
     * Distinguishes an untyped NULL from a value with a resolved native signed width.
     * @return array{ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull, bool}
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): array
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ParsedNull) {
            return [new NullLiteral(), false];
        }
        $type = $binding->typeOf($expression);
        Check::input($type instanceof Known && ($type->descriptor === Builtin::Int4 || $type->descriptor === Builtin::Int8), 'A signed comparison operand must resolve to pg_catalog.int4, pg_catalog.int8 or NULL.');
        $wide = $type->descriptor === Builtin::Int8;
        if ($expression instanceof Cast && !$expression->type->setOf) {
            $inner = $expression->operand;
            while ($inner instanceof Grouped) {
                $inner = $inner->operand;
            }
            if ($inner instanceof ParsedNull) {
                return [$wide ? new WideIntegerNull() : new NullLiteral(), true];
            }
        }
        $value = $expression instanceof ParsedColumn ? $binding->field($expression) : (new ExpressionLowering())->integerLiteral($expression, $catalogFirst);
        Check::input(
            $wide ? $value instanceof WideIntegerColumnReference || $value instanceof WideIntegerLiteral
                : $value instanceof ColumnReference || $value instanceof IntegerLiteral,
            'A signed comparison operand requires a pure signed integer or NULL.',
        );

        return [$value, true];
    }
}
