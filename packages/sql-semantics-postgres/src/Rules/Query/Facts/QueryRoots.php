<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\ValueGraph;

/**
 * Derives a query that is a whole statement: its rows, or the table SELECT INTO creates.
 *
 * Rule: PG-SELECT-INTO-001. A query statement returns its rows. When the
 * first selection of the statement (the one reached through parentheses,
 * WITH clauses and the first operands of set operations) has an INTO clause,
 * the statement returns no rows and declares the new table instead: one
 * column per output field, of the field's type and able to be NULL, as far as
 * the types are known; a table with a field of a type that is not known is
 * declared incomplete up to that field. INTO anywhere else is reported.
 * Source: https://www.postgresql.org/docs/17/sql-selectinto.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class QueryRoots
{
    /**
     * Derives the statement and records its rows or its declaration.
     */
    public function derive(Query $root, Derivation $derivation): void
    {
        $fact = $derivation->query($root, $derivation->environment());
        $first = $this->first($root);
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($root) as $object) {
            if ($object instanceof Select && $object->into !== null && $object !== $first) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::IntoNotAllowed));
            }
        }
        $into = $first?->into;
        if ($into === null) {
            $derivation->output($fact);

            return;
        }
        $derivation->declare($this->table($into, $fact, $derivation));
    }

    /**
     * Answers the first selection of a statement, whose INTO clause the statement may have.
     */
    public function first(Query $query): ?Select
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression || $query instanceof SetOperation) {
            $query = match (true) {
                $query instanceof ParenthesizedQuery => $query->query,
                $query instanceof QueryExpression => $query->body,
                $query instanceof SetOperation => $query->left,
            };
        }

        return $query instanceof Select ? $query : null;
    }

    /**
     * Answers the table SELECT INTO declares.
     */
    public function table(IntoClause $into, QueryFact $fact, Derivation $derivation): Table
    {
        $columns = [];
        $complete = true;
        foreach ($fact->projection as $item) {
            if (!$item instanceof Field || $item->name === null || !$item->type instanceof Known) {
                $complete = false;
                break;
            }
            $columns[] = new Column($item->name, $item->type->descriptor, Nullability::Nullable);
        }

        return new Table($into->table, $derivation->context->profile, $columns, [], $complete);
    }
}
