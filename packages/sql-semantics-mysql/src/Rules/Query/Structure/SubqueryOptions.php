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
        $problem = $this->problem($query);
        Check::input($problem === null, $problem ?? '');
    }

    /**
     * Describes the first option of the subquery that belongs to a statement query only, in the words of the invariant it breaks, or null when the subquery carries none.
     */
    public function problem(Query $query): ?string
    {
        $pending = [$query];
        $seen = new SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (($value instanceof Query || $value instanceof LeadingUnion) && !isset($seen[$value])) {
                $seen[$value] = true;
                if ($value instanceof Select && in_array(SelectOption::Cache, $value->options, true)) {
                    return 'SQL_CACHE is not an option of a subquery.';
                }
                if ($value instanceof Select && $value->procedure !== null) {
                    return 'An expression subquery cannot invoke PROCEDURE ANALYSE.';
                }
                if (($value instanceof QueryStatement || $value instanceof Select) && $value->into !== null) {
                    return 'An expression subquery cannot have an INTO destination.';
                }
                if (!$value instanceof Select) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
        return (new DerivedOptions())->problem($query) === null ? null : 'An expression subquery cannot carry statement-level SELECT modifiers.';
    }
}
