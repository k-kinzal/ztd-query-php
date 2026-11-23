<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
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
    public function lower(BoundStatement $operation, DefaultExpression $clause, IntegerStorage|CharacterStorage $kind): IntegerLiteral|TextLiteral|NullLiteral
    {
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        $default = (new ExpressionLowering())->lower($clause->value, new SourceBinding($operation, null), $catalogFirst, $kind === CharacterStorage::Text);
        Check::input($default instanceof IntegerLiteral || $default instanceof TextLiteral || $default instanceof NullLiteral, 'The column requires an integer, text or NULL default.');

        return $default;
    }
}
