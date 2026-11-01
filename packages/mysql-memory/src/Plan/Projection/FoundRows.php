<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Query;

/**
 * Reads SQL_CALC_FOUND_ROWS from the first SELECT of a query expression.
 *
 * @visibility MySqlMemory
 */
final class FoundRows
{
    /**
     * Answers whether the leading SELECT requests the unbounded row count.
     */
    public function requested(Query $query): bool
    {
        $first = $query;
        while ($first !== null && !$first instanceof Select) {
            $first = match (true) {
                $first instanceof QueryStatement, $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery => $first->query,
                $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression => $first->body,
                $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation && $first->left instanceof Query => $first->left,
                default => null,
            };
        }

        return $first instanceof Select && in_array(SelectOption::CalcFoundRows, $first->options, true);
    }
}
