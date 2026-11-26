<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;

/**
 * Resolves a literal in the destination column's default expression context.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class LiteralDefaultLowering
{
    /**
     * Resolves and narrows the supported default roles.
     */
    public function lower(
        BoundStatement $operation,
        DefaultExpression $clause,
        IntegerStorage|CharacterStorage|BooleanStorage $kind
    ): IntegerLiteral|WideIntegerLiteral|TextLiteral|BooleanLiteral|NullLiteral {
        if ($kind === BooleanStorage::Boolean) {
            return (new BooleanDefaultLowering())->lower($operation, $clause->value);
        }
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        $default = (new ExpressionLowering())->lower($clause->value, new SourceBinding($operation, null), $catalogFirst, $kind === CharacterStorage::Text);
        if ($default instanceof BuiltinCast && $default->target === CastDestination::WideInteger
            && ($default->operand instanceof IntegerLiteral || $default->operand instanceof WideIntegerLiteral)) {
            return new WideIntegerLiteral($default->operand->decimal);
        }
        Check::input($default instanceof IntegerLiteral || $default instanceof WideIntegerLiteral
            || $default instanceof TextLiteral || $default instanceof NullLiteral, 'The column requires an integer, text or NULL default.');

        return $default;
    }
}
