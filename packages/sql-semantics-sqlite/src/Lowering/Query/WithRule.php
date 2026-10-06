<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\Materialization;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;

/**
 * Lowers WITH clauses.
 *
 * Rule: SQLITE-WITH-LOWER-001. Scope: with, wqlist, wqitem, wqas, withnm.
 * The common table expressions keep their written order, column lists and
 * materialization hints. Terminates: the list is flattened iteratively.
 * Source: https://sqlite.org/lang_with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class WithRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the `with` of a data change statement: the clause, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $with): ?WithClause
    {
        $form = $this->lowering->productions->form($with);

        return match ($form->signature) {
            'with:' => null,
            'with: WITH wqlist' => $this->clause($form->node(1), false),
            'with: WITH RECURSIVE wqlist' => $this->clause($form->node(2), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `wqlist` into a WITH clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function clause(Node $list, bool $recursive): WithClause
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'wqlist: wqitem' && $form->signature !== 'wqlist: wqlist COMMA wqitem') {
            throw ImplementationGap::production($form);
        }
        $tables = [];
        foreach ((new Lists())->items($list) as $item) {
            $tables[] = $this->table($item);
        }

        return new WithClause($tables, $recursive);
    }

    /**
     * Lowers a `wqitem` into a common table expression.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function table(Node $item): CommonTable
    {
        $productions = $this->lowering->productions;
        $form = $productions->form($item);
        if ($form->signature !== 'wqitem: withnm eidlist_opt wqas LP select RP') {
            throw ImplementationGap::production($form);
        }
        $named = $productions->form($form->node(0));
        if ($named->signature !== 'withnm: nm') {
            throw ImplementationGap::production($named);
        }
        $name = $this->lowering->names->name($named->node(0));
        $columns = $this->lowering->ordering->optionalColumns($form->node(1)) ?? [];
        $hint = $productions->form($form->node(2));
        $materialization = match ($hint->signature) {
            'wqas: AS' => null,
            'wqas: AS MATERIALIZED' => Materialization::Materialized,
            'wqas: AS NOT MATERIALIZED' => Materialization::NotMaterialized,
            default => throw ImplementationGap::production($hint),
        };

        return new CommonTable($name, $this->lowering->selects->select($form->node(4)), $columns, $materialization);
    }
}
