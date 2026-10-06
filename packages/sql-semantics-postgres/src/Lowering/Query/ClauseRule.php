<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSet;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the clauses of a selection and the clauses written after a query body.
 *
 * Rule: PG-CLAUSE-LOWER-001. Scope: `where_clause`, `group_clause`,
 * `group_by_list`, `group_by_item`, `empty_grouping_set`, `rollup_clause`,
 * `cube_clause`, `grouping_sets_clause`, `having_clause`, `window_clause`,
 * `window_definition_list`, `window_definition`, `opt_sort_clause`,
 * `sort_clause`, `sortby_list`, `sortby`, `opt_asc_desc`, `opt_nulls_order`.
 * Constructors: `SortItem`, `GroupingSet`, `WindowDefinition`,
 * `OutputPosition`, `SelectOptions`. An integer constant written as an
 * ORDER BY, GROUP BY or DISTINCT ON term becomes an `OutputPosition`
 * (PG-OUTPUT-POSITION-001); in the ORDER BY of an aggregate or a window it
 * stays a constant. Termination: lists are flattened iteratively; grouping
 * sets recurse on the tree depth.
 * Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ClauseRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the ORDER BY, LIMIT and locking clauses written after a query body; none is null.
     */
    public function options(Node $sort, ?Node $limit, ?Node $locking, bool $lockingFirst): ?SelectOptions
    {
        $order = [];
        foreach ($this->sortClause($sort) as $item) {
            $order[] = new SortItem($this->positioned([$item->expression], OrderingClause::OrderBy)[0], $item->direction, $item->using, $item->nulls);
        }
        $rows = $limit === null ? null : (new LimitRule($this->lowering))->limit($limit);
        [$clauses, $readOnly] = $locking === null ? [[], false] : (new LimitRule($this->lowering))->locking($locking);
        if ($order === [] && $rows === null && $clauses === [] && !$readOnly) {
            return null;
        }

        return new SelectOptions($order, $rows, $clauses, $readOnly, $lockingFirst && $rows !== null && ($clauses !== [] || $readOnly));
    }

    /**
     * Wraps the integer constants among ORDER BY, GROUP BY or DISTINCT ON terms as output positions.
     *
     * @param list<Scalar> $terms
     * @return list<Scalar>
     */
    public function positioned(array $terms, OrderingClause $clause): array
    {
        $result = [];
        foreach ($terms as $term) {
            $result[] = (new Positions())->value($term) === null ? $term : new OutputPosition($term, $clause);
        }

        return $result;
    }

    /**
     * Reads expressions in a grouping position: a row written without ROW, in any parentheses, is a list of grouping terms, and an integer constant is an output position.
     *
     * @param list<Scalar> $terms
     * @return list<Scalar|GroupingRow>
     */
    public function groupingTerms(array $terms): array
    {
        $result = [];
        foreach ($terms as $term) {
            $layers = 0;
            $inner = $term;
            while ($inner instanceof Grouped) {
                $inner = $inner->operand;
                $layers++;
            }
            if (!$inner instanceof RowConstructor || $inner->spelling !== RowSpelling::Implicit) {
                $result[] = $this->positioned([$term], OrderingClause::GroupBy)[0];
                continue;
            }
            $row = new GroupingRow($this->groupingTerms($inner->fields));
            for (; $layers > 0; $layers--) {
                $row = new GroupingRow([$row]);
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Lowers `sort_clause`, `opt_sort_clause` or `sortby_list` without reading positions; no clause is an empty list.
     *
     * @return list<SortItem>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function sortClause(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        $list = match ($form->signature) {
            'opt_sort_clause:' => null,
            'opt_sort_clause: sort_clause' => $this->lowering->productions->form($form->node(0))->node(2),
            'sort_clause: ORDER BY sortby_list' => $form->node(2),
            'sortby_list: sortby', 'sortby_list: sortby_list , sortby' => $clause,
            default => throw ImplementationGap::production($form),
        };
        $items = [];
        foreach ($list === null ? [] : $this->lowering->items($list, 'sortby_list: sortby', 'sortby_list: sortby_list , sortby') as $sortby) {
            $item = $this->lowering->productions->form($sortby);
            $items[] = match ($item->signature) {
                'sortby: a_expr USING qual_all_Op opt_nulls_order' => new SortItem($this->lowering->expressions->expression($item->node(0)), null, $this->lowering->operators->operator($item->node(2)), $this->nullsOrder($item->node(3))),
                'sortby: a_expr opt_asc_desc opt_nulls_order' => new SortItem($this->lowering->expressions->expression($item->node(0)), $this->sortDirection($item->node(1)), null, $this->nullsOrder($item->node(2))),
                default => throw ImplementationGap::production($item),
            };
        }

        return $items;
    }

    /**
     * Lowers `opt_asc_desc`; no direction is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function sortDirection(Node $direction): ?SortDirection
    {
        $form = $this->lowering->productions->form($direction);

        return match ($form->signature) {
            'opt_asc_desc: ASC' => SortDirection::Ascending,
            'opt_asc_desc: DESC' => SortDirection::Descending,
            'opt_asc_desc:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_nulls_order`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function nullsOrder(Node $order): ?NullsOrder
    {
        $form = $this->lowering->productions->form($order);

        return match ($form->signature) {
            'opt_nulls_order: NULLS_LA FIRST_P' => NullsOrder::First,
            'opt_nulls_order: NULLS_LA LAST_P' => NullsOrder::Last,
            'opt_nulls_order:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `where_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'where_clause:' => null,
            'where_clause: WHERE a_expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `having_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function having(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'having_clause:' => null,
            'having_clause: HAVING a_expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `group_clause`: the quantifier and the items; no clause has neither.
     *
     * @return array{SetQuantifier|null, list<Scalar|GroupingRow|GroupingSet>}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function group(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'group_clause:' => [null, []],
            'group_clause: GROUP_P BY set_quantifier group_by_list' => [(new SelectRule($this->lowering))->quantifier($form->node(2)), $this->groupItems($form->node(3))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `group_by_list`.
     *
     * @return list<Scalar|GroupingRow|GroupingSet>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function groupItems(Node $list): array
    {
        $items = [];
        foreach ($this->lowering->items($list, 'group_by_list: group_by_item', 'group_by_list: group_by_list , group_by_item') as $item) {
            $form = $this->lowering->productions->form($item);
            $items[] = match ($form->signature) {
                'group_by_item: a_expr' => $this->groupingTerms([$this->lowering->expressions->expression($form->node(0))])[0],
                'group_by_item: empty_grouping_set', 'group_by_item: cube_clause', 'group_by_item: rollup_clause', 'group_by_item: grouping_sets_clause' => $this->groupingSet($form->node(0)),
                default => throw ImplementationGap::production($form),
            };
        }

        return $items;
    }

    /**
     * Lowers `empty_grouping_set`, `rollup_clause`, `cube_clause` or `grouping_sets_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function groupingSet(Node $set): GroupingSet
    {
        $form = $this->lowering->productions->form($set);

        return match ($form->signature) {
            'empty_grouping_set: ( )' => new GroupingSet(GroupingSetKind::Empty),
            'rollup_clause: ROLLUP ( expr_list )' => new GroupingSet(GroupingSetKind::Rollup, $this->groupingTerms($this->lowering->expressions->expressions($form->node(2)))),
            'cube_clause: CUBE ( expr_list )' => new GroupingSet(GroupingSetKind::Cube, $this->groupingTerms($this->lowering->expressions->expressions($form->node(2)))),
            'grouping_sets_clause: GROUPING SETS ( group_by_list )' => new GroupingSet(GroupingSetKind::Sets, $this->groupItems($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `window_clause`; no clause is an empty list.
     *
     * @return list<WindowDefinition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function windows(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'window_clause:') {
            return [];
        }
        if ($form->signature !== 'window_clause: WINDOW window_definition_list') {
            throw ImplementationGap::production($form);
        }
        $windows = [];
        foreach ($this->lowering->items($form->node(1), 'window_definition_list: window_definition', 'window_definition_list: window_definition_list , window_definition') as $definition) {
            $window = $this->lowering->productions->form($definition);
            if ($window->signature !== 'window_definition: ColId AS window_specification') {
                throw ImplementationGap::production($window);
            }
            $windows[] = new WindowDefinition($this->lowering->names->name($window->node(0)), $this->lowering->invocations->window($window->node(2)));
        }

        return $windows;
    }
}
