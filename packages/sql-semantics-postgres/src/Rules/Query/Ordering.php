<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSet;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Validation\Equivalence;

/**
 * Derives ORDER BY, DISTINCT ON and GROUP BY terms in the environment PostgreSQL resolves each of them in.
 *
 * Rule: PG-ORDERING-001. An output position denotes the output field at that
 * position (PG-OUTPUT-POSITION-002); any other constant is reported. In ORDER
 * BY and DISTINCT ON, a term that is one unqualified name, possibly in
 * parentheses, and equals the name of output fields denotes that field
 * before any input column; output fields with the name that compute
 * different expressions make it ambiguous. In GROUP BY such a name denotes an
 * input column when the query level has one, and an output field otherwise.
 * Every other term is an expression over the input columns of the selection.
 * After a set operation or VALUES, ORDER BY sees only the output fields;
 * after a set operation a term that is not an output name or position is
 * reported. Terminates: one pass over the terms; grouping sets are walked
 * with a work list.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-GROUPBY,
 * https://www.postgresql.org/docs/17/queries-union.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Ordering
{
    /**
     * Derives the ORDER BY or DISTINCT ON terms of a selection.
     *
     * @param list<Scalar> $terms
     * @param list<Field|OpenStar> $projection
     */
    public function sort(array $terms, array $projection, Derivation $derivation, Environment $rows, OrderingClause $clause): void
    {
        foreach ($terms as $term) {
            $name = $this->bare($term);
            $matches = $name === null ? [] : $this->named($projection, $name, $derivation);
            if ($matches !== []) {
                $derivation->scalar($term, new Environment($derivation->context, null, [], [], $matches));
                continue;
            }
            $this->term($term, $projection, $derivation, $rows, $clause);
        }
    }

    /**
     * Derives the GROUP BY items of a selection, the members of grouping sets included.
     *
     * @param list<Scalar|GroupingSet> $items
     * @param list<Field|OpenStar> $projection
     */
    public function group(array $items, array $projection, Derivation $derivation, Environment $rows): void
    {
        $pending = array_reverse($items);
        while ($pending !== []) {
            $item = array_pop($pending);
            if ($item instanceof GroupingSet) {
                array_push($pending, ...array_reverse($item->members));
                continue;
            }
            $name = $this->bare($item);
            $local = new Environment($derivation->context, null, $rows->relations);
            $matches = $name === null || !(new ColumnLookup())->find($local, $name) instanceof MissingColumn ? [] : $this->named($projection, $name, $derivation);
            if ($matches !== []) {
                $derivation->scalar($item, new Environment($derivation->context, null, [], [], $matches));
                continue;
            }
            $this->term($item, $projection, $derivation, $rows, OrderingClause::GroupBy);
        }
    }

    /**
     * Derives the ORDER BY terms written after a set operation or VALUES, which see the output fields only.
     *
     * @param list<Scalar> $terms
     * @param list<Field|OpenStar> $projection
     */
    public function outputs(array $terms, array $projection, Derivation $derivation, Environment $outer, bool $setOperation): void
    {
        $fields = $this->known($projection);
        foreach ($terms as $term) {
            if ($term instanceof OutputPosition) {
                $derivation->scalar($term, $this->positions($projection, new Environment($derivation->context)));
                continue;
            }
            if ((new Positions())->misused($term)) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::NonIntegerConstant, new Name(OrderingClause::OrderBy->value)));
            } elseif ($setOperation && $this->bare($term) === null) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::SetOperationOrderByExpression));
            }
            $derivation->scalar($term, new Environment($derivation->context, $outer, [], [], $fields));
        }
    }

    /**
     * Derives one term that is not an output name: a position, a misused constant or an expression over the input columns.
     *
     * @param list<Field|OpenStar> $projection
     */
    public function term(Scalar $term, array $projection, Derivation $derivation, Environment $rows, OrderingClause $clause): void
    {
        if ($term instanceof OutputPosition) {
            $derivation->scalar($term, $this->positions($projection, $rows));

            return;
        }
        if ((new Positions())->misused($term)) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::NonIntegerConstant, new Name($clause->value)));
        }
        $derivation->scalar($term, $rows);
    }

    /**
     * Answers the environment an output position is derived in: the known fields, and the relations of an unexpanded star.
     *
     * @param list<Field|OpenStar> $projection
     */
    public function positions(array $projection, Environment $rows): Environment
    {
        $fields = $this->known($projection);
        $open = count($fields) < count($projection);

        return new Environment($rows->context, null, $open ? $rows->relations : [], [], $fields);
    }

    /**
     * Answers the fields before the first star that could not be expanded.
     *
     * @param list<Field|OpenStar> $projection
     * @return list<Field>
     */
    public function known(array $projection): array
    {
        $fields = [];
        foreach ($projection as $item) {
            if (!$item instanceof Field) {
                break;
            }
            $fields[] = $item;
        }

        return $fields;
    }

    /**
     * Answers the output fields with a name, keeping one of those that compute the same expression.
     *
     * @param list<Field|OpenStar> $projection
     * @return list<Field>
     */
    public function named(array $projection, Name $name, Derivation $derivation): array
    {
        $matches = [];
        foreach ($projection as $item) {
            if (!$item instanceof Field || $item->name === null || !$derivation->context->columnNames->equal($item->name->value, $name->value)) {
                continue;
            }
            $same = false;
            foreach ($matches as $match) {
                $same = $same || $this->same($match, $item);
            }
            if (!$same) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    /**
     * Tells whether two output fields compute the same expression.
     */
    public function same(Field $first, Field $second): bool
    {
        if ($first->expression === null || $second->expression === null) {
            return $first->expression === null && $second->expression === null && $first->slot->origin === $second->slot->origin;
        }

        return (new Equivalence())->difference($first->expression, $second->expression) === null;
    }

    /**
     * Answers the name a term consists of when it is one unqualified name, possibly in parentheses.
     */
    public function bare(Scalar $term): ?Name
    {
        while ($term instanceof Grouped) {
            $term = $term->operand;
        }

        return $term instanceof ColumnReference && count($term->parts) === 1 ? $term->parts[0] : null;
    }
}
