<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

/**
 * The column actions without an operand.
 *
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the keywords of a column action
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind::DropIdentityIfExists->value // => 'DROP IDENTITY IF EXISTS'
 */
enum ColumnActionKind: string
{
    case SetNotNull = 'SET NOT NULL';
    case DropNotNull = 'DROP NOT NULL';
    case DropDefault = 'DROP DEFAULT';
    case DropExpression = 'DROP EXPRESSION';
    case DropExpressionIfExists = 'DROP EXPRESSION IF EXISTS';
    case DropIdentity = 'DROP IDENTITY';
    case DropIdentityIfExists = 'DROP IDENTITY IF EXISTS';

    /**
     * Tells whether IF EXISTS is written: a missing column or property is not an error.
     */
    public function conditional(): bool
    {
        return $this === self::DropExpressionIfExists || $this === self::DropIdentityIfExists;
    }
}
