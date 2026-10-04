<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the output name of a select list item.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. An alias names the item. An unaliased
 * column reference, also in parentheses (which create no item in the
 * server), is named by the column name as the reference writes it. Any
 * other unaliased expression is named by the server after the text of the
 * expression as the statement spells it, which the model does not keep, so
 * its name is not fixed: the slot has no name, and a lookup by name does not
 * find it. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ItemNaming
{
    /**
     * Answers the output name of an item, or null when the name depends on the spelling of the expression.
     */
    public function name(SelectExpression $item): ?Name
    {
        if ($item->alias !== null) {
            return $item->alias;
        }
        $expression = $item->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression instanceof ColumnUse ? $expression->name : null;
    }
}
