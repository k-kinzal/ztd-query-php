<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Statement\Query;

/**
 * Defines what the subquery of IN, ANY, ALL and SOME cannot carry.
 *
 * No release executes such a subquery with a LIMIT on its own spine: the server refuses it
 * once the statement is read, before any table is opened, in every position and inside a
 * stored program body (ER_NOT_SUPPORTED_YET; `SELECT 1 WHERE 1 IN (SELECT 1 FROM missing_t LIMIT 1)`
 * -> 1235, `SELECT 1 WHERE 1 IN (SELECT 1 LIMIT 1) FROM` -> 1064, `... IN ((SELECT 1) LIMIT 1)` and
 * `... IN (SELECT 1 UNION SELECT 2 LIMIT 1)` -> 1235, verified on 8.4.7; the 5.6 and 5.7 grammars
 * admit no LIMIT there). A LIMIT of a derived table inside the subquery, an ORDER BY, and the
 * LIMIT of an EXISTS or scalar subquery are accepted.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QuantifiedSubqueries
{
    /**
     * Answers the condition of a LIMIT on the spine of the subquery (SubqueryRows::limited), or null.
     */
    public function problem(Query $query): ?NotSupportedYet
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows())->limited($query) ? new NotSupportedYet('LIMIT & IN/ALL/ANY/SOME subquery') : null;
    }
}
