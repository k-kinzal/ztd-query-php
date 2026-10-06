<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\CommaLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\FetchFirst;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Derives the LIMIT, OFFSET and FETCH clauses and checks the locking clauses of a query.
 *
 * Rule: PG-LIMIT-001. The count and the offset are evaluated once and see
 * no column of their own query, only the enclosing queries. LIMIT with a
 * comma is reported. WITH TIES without ORDER BY, and WITH TIES together with
 * SKIP LOCKED, are reported. A locking clause names FROM items of its
 * query, unqualified, by alias or table name; a name no FROM item has is
 * reported. Terminates: one pass over the clauses.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Limits
{
    /**
     * Derives the count and offset expressions of the options, which see the enclosing queries only.
     */
    public function derive(?SelectOptions $options, Derivation $derivation, Environment $outer): void
    {
        $limit = $options?->limit;
        if ($limit === null) {
            return;
        }
        $level = new Environment($derivation->context, $outer);
        $count = $limit->count;
        if ($count instanceof CommaLimit) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::CommaLimit));
        }
        $expressions = [];
        if ($count instanceof LimitCount || $count instanceof FetchFirst) {
            $expressions[] = $count->count;
        } elseif ($count instanceof CommaLimit) {
            $expressions = [$count->count, $count->offset];
        }
        $expressions[] = $limit->offset?->start;
        foreach ($expressions as $expression) {
            if ($expression !== null) {
                $derivation->scalar($expression, $level);
            }
        }
    }

    /**
     * Reports WITH TIES without ORDER BY and WITH TIES with SKIP LOCKED.
     *
     * @param list<SelectOptions> $layers The options that apply to one query, innermost first
     */
    public function ties(array $layers, Derivation $derivation): void
    {
        $ordered = false;
        $ties = false;
        $skip = false;
        foreach ($layers as $layer) {
            $ordered = $ordered || $layer->order !== [];
            $count = $layer->limit?->count;
            $ties = $ties || ($count instanceof FetchFirst && $count->withTies);
            foreach ($layer->locking as $clause) {
                $skip = $skip || $clause->wait === LockWait::SkipLocked;
            }
        }
        if ($ties && !$ordered) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::TiesWithoutOrderBy));
        }
        if ($ties && $skip) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::TiesWithSkipLocked));
        }
    }

    /**
     * Reports the relations of locking clauses that are qualified or that no FROM item of the query has.
     *
     * @param list<LockingClause> $clauses
     * @param list<VisibleRelation> $visible The relations of the FROM items of the query
     */
    public function locked(array $clauses, array $visible, Derivation $derivation): void
    {
        $scope = new Environment($derivation->context);
        foreach ($clauses as $clause) {
            foreach ($clause->relations as $name) {
                if ($name->schema !== null) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::LockedRelationQualified));
                    continue;
                }
                $found = false;
                foreach ($visible as $relation) {
                    $found = $found || (new Visibility())->admits($scope, $relation, new QualifiedName($name->name));
                }
                if (!$found) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::LockedRelationNotInFrom, $name->name));
                }
            }
        }
    }
}
