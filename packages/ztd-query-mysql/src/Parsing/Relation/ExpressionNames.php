<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Relation;

use PhpMyAdmin\SqlParser\Components\Expression;

/**
 * Resolves relation names and aliases from parser expressions.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ExpressionNames
{
    /**
     * Return the named table, falling back to the original expression.
     */
    public static function table(Expression $expression): ?string
    {
        return ($expression->table ?? '') !== '' ? $expression->table : $expression->expr;
    }

    /**
     * Return the explicit alias or the relation's table name.
     */
    public static function alias(Expression $expression): ?string
    {
        return ($expression->alias ?? '') !== '' ? $expression->alias : self::table($expression);
    }
}
