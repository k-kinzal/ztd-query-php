<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Statement\Query;

/**
 * Checks options whose placement is intrinsic to a subquery boundary.
 *
 * HIGH_PRIORITY, SQL_BUFFER_RESULT and SQL_CALC_FOUND_ROWS belong to the
 * outer query block. SQL_CACHE is available only before MySQL 8.0 and those releases do not
 * accept it in the query blocks of an expression subquery. A derived table
 * enforces its independent modifier domain at construction. A parenthesized top-level SELECT can still
 * carry it, so parentheses alone do not impose this constraint.
 * Verified against isolated MySQL 5.6.51 and 5.7.44 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SubqueryOptions
{
    /**
     * Refuses statement-level modifiers in the query-expression spine of an expression subquery.
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
            } elseif (($value instanceof Query || $value instanceof LeadingUnion) && !isset($seen[$value])) {
                $seen[$value] = true;
                Check::input(!$value instanceof Select || !in_array(SelectOption::Cache, $value->options, true), 'SQL_CACHE is not an option of a subquery.');
                Check::input(!$value instanceof Select || $value->procedure === null, 'An expression subquery cannot invoke PROCEDURE ANALYSE.');
                Check::input(!($value instanceof QueryStatement || $value instanceof Select) || $value->into === null, 'An expression subquery cannot have an INTO destination.');
                if (!$value instanceof Select) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
        Check::input((new DerivedOptions())->problem($query) === null, 'An expression subquery cannot carry statement-level SELECT modifiers.');
    }
}
