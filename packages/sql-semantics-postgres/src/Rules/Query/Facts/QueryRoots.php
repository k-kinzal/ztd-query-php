<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTypes;
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
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\ValueGraph;

/**
 * Derives a query that is a whole statement: its rows, or the table SELECT INTO creates.
 *
 * Rule: PG-SELECT-INTO-001. A query statement returns its rows. When the
 * first selection of the statement (the one reached through parentheses,
 * WITH clauses and the first operands of set operations) has an INTO clause,
 * the statement returns no rows and declares the new table instead: one
 * column per output field, of the type the field gives a defined column
 * (PG-DECLARED-TYPE-001) and able to be NULL; a table with an output whose
 * name or position is not known (an open row) or whose type is invalid is
 * declared incomplete up to that output. INTO anywhere else is reported. Data-modifying common tables
 * outside the top-level WITH clause are reported (PG-MODIFYING-CTE-001).
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
        $modifying = new ModifyingCommonTables();
        $modifying->check($root, $modifying->top($root), $derivation);
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
        $types = new DeclaredTypes();
        foreach ($fact->projection as $item) {
            $type = $item instanceof Field ? $types->defined($item->type) : null;
            if (!$item instanceof Field || $item->name === null || $type === null) {
                $complete = false;
                break;
            }
            $columns[] = new Column($item->name, $type, Nullability::Nullable);
        }

        return new Table($into->table, $derivation->context->profile, $columns, [], $complete);
    }
}
