<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Hands the ORDER BY and locking clauses written after parentheses down to the selection inside them.
 *
 * Rule: PG-QUERY-OPTIONS-001. PostgreSQL attaches the clauses written after
 * a parenthesized query to the query inside the parentheses, so that ORDER BY
 * and FOR UPDATE after `(SELECT … FROM t)` see `t`. The query expression that
 * holds such clauses passes itself to the body as a carrier in the
 * environment: a common table binding under the empty name, which no SQL name
 * can refer to. The selection or TABLE query the clauses belong to takes
 * the carriers addressed to it and derives their clauses in its own
 * environment; every other query ignores them.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Carriers
{
    /**
     * Answers the query a body stands for once parentheses and the clauses around it are set aside.
     */
    public function core(Query $query): Query
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query;
    }

    /**
     * Answers the environment with a carrier added at the same query level.
     */
    public function carry(Environment $environment, QueryExpression $carrier): Environment
    {
        return new Environment($environment->context, $environment->outer, $environment->relations, [...$environment->commonTables, new CommonBinding(new Name(''), $carrier, new RowShape([]))], $environment->aliases);
    }

    /**
     * Answers the environment without the carriers addressed to a query, and those carriers, outermost first.
     *
     * @return array{Environment, list<QueryExpression>}
     */
    public function take(Environment $environment, Query $query): array
    {
        $kept = [];
        $carriers = [];
        foreach ($environment->commonTables as $binding) {
            if ($binding->name->value === '' && $binding->definition instanceof QueryExpression && $this->core($binding->definition) === $query) {
                $carriers[] = $binding->definition;
            } else {
                $kept[] = $binding;
            }
        }
        if ($carriers === []) {
            return [$environment, []];
        }

        return [new Environment($environment->context, $environment->outer, $environment->relations, $kept, $environment->aliases), $carriers];
    }
}
