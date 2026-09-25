<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Recursion;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the SEARCH and CYCLE clauses of a common table expression against its columns.
 * @visibility SqlSemantics
 */
final class RecursionRules
{
    /**
     * Requires a PostgreSQL query, listed columns that the query exposes, added columns that it does not expose and
     * that differ from each other, and mark values in the query dialect.
     *
     * @throws InvalidStructure
     */
    public static function validate(CommonTableExpression $definition): void
    {
        if (!$definition->query instanceof BoundQuery || $definition->query->origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SEARCH and CYCLE clauses belong to a PostgreSQL query.');
        }
        $visible = $definition->visibleColumns();
        $listed = [...($definition->search->columns ?? []), ...($definition->cycle->columns ?? [])];
        $added = array_values(array_filter([$definition->search?->sequenceColumn, $definition->cycle?->markColumn, $definition->cycle?->pathColumn], static fn (?string $name): bool => $name !== null));
        if (array_diff($listed, $visible) !== [] || array_intersect($added, $visible) !== [] || count(array_unique($added)) !== count($added)) {
            throw new InvalidStructure('SEARCH and CYCLE list columns of the query and add columns with new, distinct names.');
        }
        StatementOperands::expressions([$definition->cycle?->markValue, $definition->cycle?->markDefault], Dialect::PostgreSql);
    }
}
