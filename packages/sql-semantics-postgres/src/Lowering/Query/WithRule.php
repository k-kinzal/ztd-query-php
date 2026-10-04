<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\CycleClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Statement\Query;

/**
 * Lowers WITH clauses and their common table expressions.
 *
 * Rule: PG-WITH-LOWER-001. Scope: `opt_with_clause`, `with_clause`,
 * `cte_list`, `common_table_expr`, `opt_materialized`, `opt_search_clause`,
 * `opt_cycle_clause`. Constructors: `WithClause`, `CommonTableExpression`,
 * `SearchClause`, `CycleClause`. The statement of a common table is lowered
 * by the manipulation family (`PreparableStmt`). Termination: lists are
 * flattened iteratively. Source: https://www.postgresql.org/docs/17/queries-with.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class WithRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `with_clause` or `opt_with_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function with(Node $clause): ?WithClause
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_with_clause:' => null,
            'opt_with_clause: with_clause' => $this->with($form->node(0)),
            'with_clause: WITH cte_list', 'with_clause: WITH_LA cte_list' => new WithClause($this->tables($form->node(1))),
            'with_clause: WITH RECURSIVE cte_list' => new WithClause($this->tables($form->node(2)), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `cte_list`.
     *
     * @return list<CommonTableExpression>
     *
     * @throws ImplementationGap When an item has no rule or its statement is not a query
     */
    public function tables(Node $list): array
    {
        $tables = [];
        foreach ($this->lowering->items($list, 'cte_list: common_table_expr', 'cte_list: cte_list , common_table_expr') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'common_table_expr: name opt_name_list AS opt_materialized ( PreparableStmt ) opt_search_clause opt_cycle_clause') {
                throw ImplementationGap::production($form);
            }
            $statement = $this->lowering->manipulations->preparable($form->node(5));
            if (!$statement instanceof Query) {
                throw ImplementationGap::rule('PG-WITH-LOWER-001: a common table statement that is not a query');
            }
            $tables[] = new CommonTableExpression(
                $this->lowering->names->name($form->node(0)),
                $statement,
                $this->lowering->names->names($form->node(1)),
                $this->materialization($form->node(3)),
                $this->search($form->node(7)),
                $this->cycle($form->node(8)),
            );
        }

        return $tables;
    }

    /**
     * Lowers `opt_materialized`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function materialization(Node $clause): ?Materialization
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_materialized: MATERIALIZED' => Materialization::Materialized,
            'opt_materialized: NOT MATERIALIZED' => Materialization::NotMaterialized,
            'opt_materialized:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_search_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function search(Node $clause): ?SearchClause
    {
        $form = $this->lowering->productions->form($clause);
        $order = match ($form->signature) {
            'opt_search_clause: SEARCH DEPTH FIRST_P BY columnList SET ColId' => SearchOrder::Depth,
            'opt_search_clause: SEARCH BREADTH FIRST_P BY columnList SET ColId' => SearchOrder::Breadth,
            'opt_search_clause:' => null,
            default => throw ImplementationGap::production($form),
        };

        return $order === null ? null : new SearchClause($order, $this->lowering->names->names($form->node(4)), $this->lowering->names->name($form->node(6)));
    }

    /**
     * Lowers `opt_cycle_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function cycle(Node $clause): ?CycleClause
    {
        $form = $this->lowering->productions->form($clause);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'opt_cycle_clause: CYCLE columnList SET ColId TO AexprConst DEFAULT AexprConst USING ColId' => new CycleClause(
                $names->names($form->node(1)),
                $names->name($form->node(3)),
                $names->name($form->node(9)),
                $this->lowering->literals->constant($form->node(5)),
                $this->lowering->literals->constant($form->node(7)),
            ),
            'opt_cycle_clause: CYCLE columnList SET ColId USING ColId' => new CycleClause($names->names($form->node(1)), $names->name($form->node(3)), $names->name($form->node(5))),
            'opt_cycle_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
