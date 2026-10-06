<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers;
use SqlSemantics\Platform\PostgreSql\Rules\Query\DistinctOrdering;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Limits;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Locking;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering;
use SqlSemantics\Platform\PostgreSql\Rules\Query\StarExpansion;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSet;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Query\TableQuery;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of a selection and of a TABLE query.
 *
 * Rule: PG-SELECT-SCOPE-001. The FROM items are derived in the environment
 * of the enclosing query (PG-FROM-SCOPE-001). WHERE, the select list,
 * GROUP BY, HAVING, the named windows, DISTINCT ON and ORDER BY see the
 * relations of the FROM items and, beyond them, the enclosing queries; the
 * select list does not see its own output names, and GROUP BY, DISTINCT ON
 * and ORDER BY follow PG-ORDERING-001. LIMIT and OFFSET see only the
 * enclosing queries (PG-LIMIT-001). The ORDER BY and locking clauses written
 * after parentheses around the selection are its own (PG-QUERY-OPTIONS-001).
 * With grouping sets a grouped column is NULL in the rows of the sets that do
 * not group it, so every output field can be NULL. Locking is reported with
 * DISTINCT, GROUP BY and HAVING. The output fields follow PG-TARGET-001 and
 * PG-STAR-001. Terminates: every clause is a strict part of the selection.
 * Source: https://www.postgresql.org/docs/17/sql-select.html,
 * https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-GROUPING-SETS. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SelectFacts
{
    /**
     * Derives every part of the selection and answers its output.
     */
    public function derive(Select $select, Derivation $derivation, Environment $outer): QueryFact
    {
        [$base, $carriers] = (new Carriers())->take($outer, $select);
        $visible = $select->from === null ? [] : (new FromScope())->open($select->from, $derivation, $base, [])->visible;
        $rows = new Environment($derivation->context, $base, $visible);
        if ($select->where !== null) {
            $derivation->scalar($select->where, $rows);
        }
        $items = [];
        foreach ($select->targets as $target) {
            array_push($items, ...$target->project($derivation, $rows, count($items)));
        }
        $ordering = new Ordering();
        $ordering->group($select->groupBy, $items, $derivation, $rows);
        if ($select->having !== null) {
            $derivation->scalar($select->having, $rows);
        }
        foreach ($select->windows as $window) {
            $window->deriveClause($derivation, $rows);
        }
        if ($select->distinct !== null) {
            $ordering->sort($select->distinct->on, $items, $derivation, $rows, OrderingClause::DistinctOn);
        }
        $layers = $this->layers($select->options, $carriers);
        $this->options($layers, $items, $visible, $derivation, $rows, $base, $select->options, $select->from);
        $order = [];
        foreach ($layers as $layer) {
            foreach ($layer->order as $item) {
                $order[] = $item->expression;
            }
        }
        (new DistinctOrdering())->check($select->distinct->on ?? [], $order, $items, $derivation);
        if ($this->locks($layers)) {
            foreach ([[$select->distinct !== null, QueryMisuseRule::LockingWithDistinct], [$select->groupBy !== [], QueryMisuseRule::LockingWithGroupBy], [$select->having !== null, QueryMisuseRule::LockingWithHaving]] as [$present, $rule]) {
                if ($present) {
                    $derivation->report(new QueryMisuse($rule));
                }
            }
        }

        return new QueryFact($this->grouped($select, $items), $derivation->context->columnNames);
    }

    /**
     * Derives the TABLE query and answers its output: every column of the table.
     */
    public function table(TableQuery $query, Derivation $derivation, Environment $outer): QueryFact
    {
        [$base, $carriers] = (new Carriers())->take($outer, $query);
        $visible = (new FromScope())->open($query->table, $derivation, $base, [])->visible;
        $rows = new Environment($derivation->context, $base, $visible);
        $items = (new StarExpansion())->all($derivation, $rows, 0);
        $this->options($this->layers($query->options, $carriers), $items, $visible, $derivation, $rows, $base, $query->options, $query->table);

        return new QueryFact($items, $derivation->context->columnNames);
    }

    /**
     * Derives the ORDER BY items and checks the locking clauses of every layer, and derives the own limit.
     *
     * @param list<SelectOptions> $layers
     * @param list<Field|OpenStar> $items
     * @param list<VisibleRelation> $visible
     */
    public function options(array $layers, array $items, array $visible, Derivation $derivation, Environment $rows, Environment $base, ?SelectOptions $own, ?Relation $from = null): void
    {
        $limits = new Limits();
        foreach ($layers as $layer) {
            (new Ordering())->sort(array_map(static fn (SortItem $item): Scalar => $item->expression, $layer->order), $items, $derivation, $rows, OrderingClause::OrderBy);
            $limits->locked($layer->locking, $visible, $derivation);
            (new Locking())->check($layer->locking, $visible, $from, $derivation, $base);
        }
        $limits->derive($own, $derivation, $base);
        $limits->ties($layers, $derivation);
    }

    /**
     * Answers the options that apply to a query, its own first and then those of the carriers.
     *
     * @param list<\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression> $carriers
     * @return list<SelectOptions>
     */
    public function layers(?SelectOptions $own, array $carriers): array
    {
        $layers = $own === null ? [] : [$own];
        foreach (array_reverse($carriers) as $carrier) {
            if ($carrier->options !== null) {
                $layers[] = $carrier->options;
            }
        }

        return $layers;
    }

    /**
     * Tells whether any layer locks rows.
     *
     * @param list<SelectOptions> $layers
     */
    public function locks(array $layers): bool
    {
        foreach ($layers as $layer) {
            if ($layer->locking !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the output with every field able to be NULL when GROUP BY has grouping sets.
     *
     * @param list<Field|OpenStar> $items
     * @return list<Field|OpenStar>
     */
    public function grouped(Select $select, array $items): array
    {
        $sets = false;
        foreach ($select->groupBy as $item) {
            $sets = $sets || $item instanceof GroupingSet;
        }
        if (!$sets) {
            return $items;
        }
        $result = [];
        foreach ($items as $item) {
            $result[] = $item instanceof Field && $item->nullability === Nullability::NotNull
                ? new Field($item->position, new OutputSlot($item->name, $item->type, Nullability::Nullable, null, $item->slot), $item->expression, $item->resolution)
                : $item;
        }

        return $result;
    }
}
