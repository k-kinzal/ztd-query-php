<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\FromScope;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\Joining;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * Derives the facts of a selection.
 *
 * Rule: SQLITE-SELECT-SCOPE-001. The input relations are derived in the
 * enclosing environment (SQLITE-FROM-SCOPE-001). The result columns see the
 * input relations and, beyond them, the enclosing queries; they do not see
 * each other's aliases. WHERE, GROUP BY, HAVING and ORDER BY see the input
 * columns first and use a result column alias when no input column has the
 * name; ORDER BY and GROUP BY terms follow SQLITE-SORT-SCOPE-001. In an
 * aggregate selection without GROUP BY every input column read by the result
 * columns, HAVING and ORDER BY can be NULL (SQLITE-AGGREGATE-QUERY-001). The
 * named windows see the input columns. LIMIT and OFFSET see no column of the
 * selection. The output fields follow SQLITE-RESULT-NAME-001. Terminates:
 * every clause is a strict part of the selection.
 * Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectFacts
{
    /**
     * Derives every part of the selection and answers its output.
     */
    public function derive(Select $select, Derivation $derivation, Environment $outer): QueryFact
    {
        $context = $derivation->context;
        $visible = $select->from === null ? [] : (new FromScope())->open($select->from, $derivation, $outer)->visible;
        $ordering = array_map(static fn (SortTerm $term): Scalar => $term->expression, $select->orderBy);
        $single = $select->groupBy === [] && ($select->having !== null || (new Aggregation())->aggregates([...$select->columns, ...$ordering]));
        $output = $single ? (new Joining())->extend($visible) : $visible;
        $items = (new Projection())->items($select->columns, $derivation, new Environment($context, $outer, $output));
        $aliases = $this->aliases($select, $items);
        $rows = new Environment($context, $outer, $visible, [], $aliases);
        $results = new Environment($context, $outer, $output, [], $aliases);
        if ($select->where !== null) {
            $derivation->scalar($select->where, $rows);
        }
        (new SortScopes())->derive($select->groupBy, $derivation, $rows, $items, false);
        if ($select->having !== null) {
            $derivation->scalar($select->having, $results);
        }
        foreach ($select->windows as $window) {
            foreach ($window->window->expressions() as $expression) {
                $derivation->scalar($expression, new Environment($context, $outer, $output));
            }
        }
        (new SortScopes())->derive($ordering, $derivation, $results, $items, true);
        $this->limit($select->limit, $derivation, $outer);

        return new QueryFact($items, $context->columnNames);
    }

    /**
     * Answers the output fields that carry an explicit alias, in output order.
     *
     * @param list<Field|OpenStar> $items
     * @return list<Field>
     */
    public function aliases(Select $select, array $items): array
    {
        $aliased = [];
        foreach ($select->columns as $column) {
            if ($column instanceof ResultColumn && $column->alias !== null) {
                $aliased[spl_object_id($column->expression)] = true;
            }
        }
        $fields = [];
        foreach ($items as $item) {
            if ($item instanceof Field && $item->expression !== null && isset($aliased[spl_object_id($item->expression)])) {
                $fields[] = $item;
            }
        }

        return $fields;
    }

    /**
     * Derives the expressions of a LIMIT clause, which see no column of their query.
     */
    public function limit(?Limit $limit, Derivation $derivation, Environment $outer): void
    {
        foreach ([$limit?->count, $limit?->offset] as $expression) {
            if ($expression !== null) {
                $derivation->scalar($expression, new Environment($derivation->context, $outer));
            }
        }
    }
}
