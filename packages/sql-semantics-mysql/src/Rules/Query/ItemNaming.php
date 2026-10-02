<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the output name of a select list item.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. An alias names the item. An unaliased
 * column reference is named by the column name as the reference writes it.
 * Any other unaliased expression is named by the server after the text of
 * the expression as the statement spells it; that name depends on spelling
 * the model does not keep, so this rule is not written for it and reports a
 * missing rule (open question of the family plan). Slice of the query
 * family. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ItemNaming
{
    /**
     * Answers the output name of an item.
     *
     * @throws ImplementationGap When the item is an unaliased expression other than a column reference
     */
    public function name(SelectExpression $item): Name
    {
        if ($item->alias !== null) {
            return $item->alias;
        }
        if ($item->expression instanceof ColumnUse) {
            return $item->expression->name;
        }

        throw ImplementationGap::rule('MYSQL-SELECT-ITEM-NAME-001: the output name of an unaliased expression of class ' . $item->expression::class);
    }
}
