<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Statement\Query;

/**
 * Identifies the query blocks tested by EXISTS, excluding nested value queries,
 * derived tables and CTE definitions. Each wildcard of these blocks denotes
 * the constant 1, including in UNION, INTERSECT and EXCEPT operands.
 * Verified through SQL against MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/exists-and-not-exists-subqueries.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Existence
{
    /**
     * Records only the constituent query blocks, before their facts are derived.
     */
    public function mark(Query|M\Set\LeadingUnion $query, Derivation $derivation): void
    {
        if ($query instanceof M\Select || $query instanceof M\ExplicitTable) {
            $derivation->existenceQueries->offsetSet($query, null);
        } elseif ($query instanceof M\QueryExpression) {
            $this->mark($query->body, $derivation);
        } elseif ($query instanceof M\ParenthesizedQuery || $query instanceof M\QueryStatement) {
            $this->mark($query->query, $derivation);
        } elseif ($query instanceof M\Set\SetOperation || $query instanceof M\Set\OrderedSetOperation || $query instanceof M\Set\LeadingUnion) {
            $this->mark($query->left, $derivation);
            $this->mark($query->right, $derivation);
        }
    }
}
