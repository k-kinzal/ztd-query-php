<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Names the output column of a select-list item that has no alias.
 *
 * Rule: PG-TARGET-NAME-001. The expression names the column when its form
 * fixes a name (a column reference after its column, a function call after
 * the function, a cast after its type, and so on); every other column is
 * named `?column?`. The first output column of a query has such a name only
 * when the query's first item is an expression; a star leaves it open.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TargetNames
{
    /**
     * Answers the name an unaliased expression gives its output column.
     */
    public function name(Scalar $expression): Name
    {
        return ($expression instanceof OutputNaming ? $expression->outputName() : null) ?? new Name('?column?');
    }

    /**
     * Answers the name of the first output column of a select list, or null when a star comes first or the list is empty.
     *
     * @param list<Target> $targets
     */
    public function first(array $targets): ?Name
    {
        $first = $targets[0] ?? null;

        return $first instanceof ExpressionTarget ? $first->name() : null;
    }
}
