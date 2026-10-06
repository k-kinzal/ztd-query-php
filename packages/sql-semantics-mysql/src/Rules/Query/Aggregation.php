<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Statement\Query;

/**
 * Tells whether a query block aggregates its rows.
 *
 * Rule: MYSQL-AGGREGATE-QUERY-001. A query block aggregates when one of
 * the given parts holds a set function written without OVER outside any
 * nested query. Such a block without GROUP BY returns one row, also over no
 * input rows, so every input column it reads there can be NULL. Terminates:
 * the walk follows the finite structure and does not enter nested queries.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html
 * ("If you use an aggregate function in a statement containing no GROUP BY
 * clause, it is equivalent to grouping on all rows"),
 * https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Aggregation
{
    /**
     * Tells whether a set function aggregates in one of the parts, outside nested queries.
     *
     * @param list<object> $parts The structure values to search
     */
    public function aggregates(array $parts): bool
    {
        $pending = $parts;
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
                continue;
            }
            if (!is_object($value) || $value instanceof Query) {
                continue;
            }
            if ($value instanceof SetFunction && $value->aggregates()) {
                return true;
            }
            array_push($pending, ...array_values(get_object_vars($value)));
        }

        return false;
    }
}
