<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Checks options whose placement is intrinsic to a subquery boundary.
 *
 * SQL_CACHE is available only before MySQL 8.0 and those releases do not
 * accept it inside a subquery. A parenthesized top-level SELECT can still
 * carry it, so parentheses alone do not impose this constraint.
 * Verified against isolated MySQL 5.6.51 and 5.7.44 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SubqueryOptions
{
    /**
     * Refuses SQL_CACHE anywhere inside a subquery's operand graph.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a query-cache option crosses the subquery boundary
     */
    public function check(Query $query): void
    {
        $pending = [$query];
        $seen = new SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node && !isset($seen[$value])) {
                $seen[$value] = true;
                Check::input(!$value instanceof Select || !in_array(SelectOption::Cache, $value->options, true), 'SQL_CACHE is not an option of a subquery.');
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
    }
}
