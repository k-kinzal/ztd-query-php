<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Limits;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Query\TableQuery;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the facts of a query expression: its WITH clause, its body and the clauses after it.
 *
 * Rule: PG-QUERY-EXPRESSION-001. The common tables of the WITH clause are
 * visible in the body (PG-COMMON-TABLE-001). The output is that of the body.
 * ORDER BY and locking clauses that belong to a selection or TABLE query
 * inside parentheses are handed to it (PG-QUERY-OPTIONS-001); after a set
 * operation or VALUES, ORDER BY sees only the output fields
 * (PG-ORDERING-001), and locking is reported. LIMIT and OFFSET see only the
 * enclosing queries. PostgreSQL merges the clauses written at different
 * parenthesis levels into one query and reports a second ORDER BY, LIMIT,
 * OFFSET or WITH clause. Terminates: the chain of parentheses is finite.
 * Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class QueryExpressionFacts
{
    /**
     * Derives every part of the query expression and answers its output.
     */
    public function derive(QueryExpression $query, Derivation $derivation, Environment $outer): QueryFact
    {
        $environment = $query->with === null ? $outer : $query->with->deriveCommonTables($derivation, $outer);
        $carriers = new Carriers();
        $core = $carriers->core($query->body);
        $handed = $core instanceof Select || $core instanceof TableQuery;
        $fact = $derivation->query($query->body, $query->options !== null && $handed ? $carriers->carry($environment, $query) : $environment);
        $this->merged($query, $derivation);
        $options = $query->options;
        if ($options === null) {
            return $fact;
        }
        if (!$handed) {
            (new Ordering())->outputs(array_map(static fn (SortItem $item): Scalar => $item->expression, $options->order), $fact->projection, $derivation, $environment, $core instanceof SetOperation);
            if ($options->locking !== []) {
                $derivation->report(new QueryMisuse($core instanceof SetOperation ? QueryMisuseRule::LockingWithSetOperation : QueryMisuseRule::LockingOnValues));
            }
            (new Limits())->ties([$options], $derivation);
        }
        (new Limits())->derive($options, $derivation, $environment);

        return $fact;
    }

    /**
     * Reports a clause written both here and at a parenthesis level inside, which PostgreSQL cannot merge.
     */
    public function merged(QueryExpression $query, Derivation $derivation): void
    {
        $inner = [];
        $with = false;
        $body = $query->body;
        while ($body instanceof ParenthesizedQuery || $body instanceof QueryExpression) {
            if ($body instanceof QueryExpression) {
                $with = $with || $body->with !== null;
                $inner[] = $body->options;
            }
            $body = $body instanceof ParenthesizedQuery ? $body->query : $body->body;
        }
        if ($body instanceof Select || $body instanceof TableQuery) {
            $inner[] = $body->options;
        }
        $options = $query->options;
        $checks = [
            [$query->with !== null && $with, QueryMisuseRule::MultipleWith],
            [$options !== null && $options->order !== [] && $this->any($inner, static fn (SelectOptions $o): bool => $o->order !== []), QueryMisuseRule::MultipleOrderBy],
            [$options?->limit?->count !== null && $this->any($inner, static fn (SelectOptions $o): bool => $o->limit?->count !== null), QueryMisuseRule::MultipleLimit],
            [$options?->limit?->offset !== null && $this->any($inner, static fn (SelectOptions $o): bool => $o->limit?->offset !== null), QueryMisuseRule::MultipleOffset],
        ];
        foreach ($checks as [$failed, $rule]) {
            if ($failed) {
                $derivation->report(new QueryMisuse($rule));
            }
        }
    }

    /**
     * Tells whether any of the options satisfies a condition.
     *
     * @param list<SelectOptions|null> $options
     * @param callable(SelectOptions): bool $condition
     */
    public function any(array $options, callable $condition): bool
    {
        foreach ($options as $option) {
            if ($option !== null && $condition($option)) {
                return true;
            }
        }

        return false;
    }
}
