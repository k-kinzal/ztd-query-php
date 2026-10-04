<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers result column lists, aliases and VALUES clauses.
 *
 * Rule: SQLITE-RESULT-LOWER-001. Scope: selcollist, sclp, scanpt, as,
 * distinct (of a selection), values, mvalues. Result columns and rows keep
 * their written order. `scanpt` is an empty marker of the grammar that
 * carries nothing. Terminates: both lists are walked along their spine in a
 * loop. Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ResultRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `selcollist` into its result columns in written order.
     *
     * @return list<ResultColumn|Star|TableStar>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $productions = $this->lowering->productions;
        $forms = [];
        for ($node = $list; $node !== null;) {
            $form = $productions->form($node);
            $forms[] = $form;
            $prefix = $productions->form($form->node(0));
            $node = match ($prefix->signature) {
                'sclp:' => null,
                'sclp: selcollist COMMA' => $prefix->node(0),
                default => throw ImplementationGap::production($prefix),
            };
        }
        $columns = [];
        foreach (array_reverse($forms) as $form) {
            $columns[] = match ($form->signature) {
                'selcollist: sclp scanpt expr scanpt as' => new ResultColumn($this->lowering->expressions->expression($form->node(2)), $this->alias($form->node(4))),
                'selcollist: sclp scanpt STAR' => new Star(),
                'selcollist: sclp scanpt nm DOT STAR' => new TableStar($this->lowering->names->name($form->node(2))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $columns;
    }

    /**
     * Lowers an `as`: the alias, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->productions->form($alias);

        return match ($form->signature) {
            'as:' => null,
            'as: AS nm' => $this->lowering->names->name($form->node(1)),
            'as: ids' => $this->lowering->names->token($form->token(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the `distinct` of a selection: the written quantifier, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quantifier(Node $distinct): ?SetQuantifier
    {
        $form = $this->lowering->productions->form($distinct);

        return match ($form->signature) {
            'distinct:' => null,
            'distinct: DISTINCT' => SetQuantifier::Distinct,
            'distinct: ALL' => SetQuantifier::All,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `values` or `mvalues` into a VALUES clause with its rows in written order.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function values(Node $values): ValuesClause
    {
        $productions = $this->lowering->productions;
        $lists = [];
        for ($node = $values; $node !== null;) {
            $form = $productions->form($node);
            [$list, $node] = match ($form->signature) {
                'values: VALUES LP nexprlist RP' => [$form->node(2), null],
                'mvalues: values COMMA LP nexprlist RP', 'mvalues: mvalues COMMA LP nexprlist RP' => [$form->node(3), $form->node(0)],
                default => throw ImplementationGap::production($form),
            };
            $lists[] = $list;
        }
        $rows = [];
        foreach (array_reverse($lists) as $list) {
            $rows[] = new ValueRow($this->lowering->expressions->items($list));
        }

        return new ValuesClause($rows);
    }
}
