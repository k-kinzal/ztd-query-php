<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\TableQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Statement\Query;

/**
 * Tells which queries a statement may hold where the grammar writes `SelectStmt`.
 *
 * Rule: PG-QUERY-SOURCE-001. A `SelectStmt` is a selection, TABLE, VALUES,
 * a set operation, a query with WITH, ORDER BY, LIMIT or locking clauses, or
 * one of these in parentheses. For INSERT, a VALUES list alone, in any
 * number of parentheses, is the VALUES rows of the INSERT, not its query.
 * Termination: one walk down the parentheses.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Sources
{
    /**
     * Tells whether a query is written as a `SelectStmt`.
     */
    public function statement(Query $query): bool
    {
        return $query instanceof Select || $query instanceof TableQuery || $query instanceof ValuesList || $query instanceof SetOperation
            || $query instanceof QueryExpression || $query instanceof ParenthesizedQuery;
    }

    /**
     * Tells whether a query can be the query of an INSERT: a `SelectStmt` that is not VALUES alone in parentheses or none.
     */
    public function insertable(Query $query): bool
    {
        $core = $query;
        while ($core instanceof ParenthesizedQuery) {
            $core = $core->query;
        }

        return $this->statement($query) && !$core instanceof ValuesList;
    }
}
