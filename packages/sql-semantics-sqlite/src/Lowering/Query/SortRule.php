<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;

/**
 * Lowers ordering terms and column name lists.
 *
 * Rule: SQLITE-SORT-LOWER-001. Scope: sortlist, sortorder, nulls, eidlist,
 * eidlist_opt, orderby_opt. Terms keep their written order, direction and
 * NULL placement. In the ORDER BY clause of a query an integer constant is a
 * request for the result column at that position; everywhere else it is an
 * ordinary constant. Terminates: lists are flattened iteratively.
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause,
 * https://sqlite.org/syntax/indexed-column.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SortRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `sortlist` into its terms in written order.
     *
     * @param bool $ordinals Whether an integer constant names a result column, as in the ORDER BY clause of a query
     * @return list<SortTerm>
     * @throws ImplementationGap When a production has no rule
     */
    public function terms(Node $sortlist, bool $ordinals = false): array
    {
        $form = $this->lowering->productions->form($sortlist);
        if ($form->signature !== 'sortlist: expr sortorder nulls' && $form->signature !== 'sortlist: sortlist COMMA expr sortorder nulls') {
            throw ImplementationGap::production($form);
        }
        $items = (new Lists())->items($sortlist);
        $terms = [];
        for ($index = 0; $index < count($items); $index += 3) {
            $expression = $this->lowering->expressions->expression($items[$index]);
            if ($ordinals && (new Ordinals())->value($expression) !== null) {
                $expression = new OutputOrdinal($expression);
            }
            $terms[] = new SortTerm($expression, $this->direction($items[$index + 1]), $this->nulls($items[$index + 2]));
        }

        return $terms;
    }

    /**
     * Lowers an `orderby_opt` of a query: its terms, or none when the clause is absent.
     *
     * @return list<SortTerm>
     * @throws ImplementationGap When the production has no rule
     */
    public function orderBy(Node $clause, bool $ordinals = false): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'orderby_opt:' => [],
            'orderby_opt: ORDER BY sortlist' => $this->terms($form->node(2), $ordinals),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `sortorder`: the written direction, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function direction(Node $sortorder): ?SortDirection
    {
        $form = $this->lowering->productions->form($sortorder);

        return match ($form->signature) {
            'sortorder:' => null,
            'sortorder: ASC' => SortDirection::Ascending,
            'sortorder: DESC' => SortDirection::Descending,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `nulls`: the written NULL placement, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function nulls(Node $nulls): ?NullsOrder
    {
        $form = $this->lowering->productions->form($nulls);

        return match ($form->signature) {
            'nulls:' => null,
            'nulls: NULLS FIRST' => NullsOrder::First,
            'nulls: NULLS LAST' => NullsOrder::Last,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `eidlist` into its column names in written order.
     *
     * @return list<ListedColumn>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $eidlist): array
    {
        $form = $this->lowering->productions->form($eidlist);
        if ($form->signature !== 'eidlist: nm collate sortorder' && $form->signature !== 'eidlist: eidlist COMMA nm collate sortorder') {
            throw ImplementationGap::production($form);
        }
        $items = (new Lists())->items($eidlist);
        $columns = [];
        for ($index = 0; $index < count($items); $index += 3) {
            $columns[] = new ListedColumn($this->lowering->names->name($items[$index]), $this->lowering->names->collation($items[$index + 1]), $this->direction($items[$index + 2]));
        }

        return $columns;
    }

    /**
     * Lowers an `eidlist_opt`: the column names, or null when the list is absent.
     *
     * @return list<ListedColumn>|null
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalColumns(Node $list): ?array
    {
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'eidlist_opt:' => null,
            'eidlist_opt: LP eidlist RP' => $this->columns($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
