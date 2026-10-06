<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Layouts;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rendering\Canonical;
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
 * carries nothing. A result column without an alias keeps the layout of its
 * expression when it is not the canonical spelling (CORE-SPELLING-001),
 * since SQLite names the column after it; the layout includes a comment
 * written after the expression, which SQLite's name includes. Whether AS introduces an alias is
 * kept, because the text of an enclosing expression includes it. Terminates: both lists are walked along their spine in a
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
            $this->marker($form->node(1));
            $columns[] = match ($form->signature) {
                'selcollist: sclp scanpt expr scanpt as' => $this->column($form->node(2), $form->node(4)),
                'selcollist: sclp scanpt STAR' => new Star(),
                'selcollist: sclp scanpt nm DOT STAR' => new TableStar($this->lowering->names->name($form->node(2))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $columns;
    }

    /**
     * Lowers one projected expression; without an alias it keeps the spelling SQLite names it after.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $expression, Node $as): ResultColumn
    {
        $lowered = $this->lowering->expressions->expression($expression);
        $alias = $this->alias($as);
        $layout = $alias === null ? (new Layouts())->of($expression, (new Canonical())->trail($this->lowering->trivia->after($expression))) : null;

        return new ResultColumn($lowered, $alias, $layout === null || (new Canonical())->same($layout, (new Canonical())->layout($lowered)) ? null : $layout, $this->keyword($as));
    }

    /**
     * Confirms that a `scanpt` is the empty marker of the grammar, which carries nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function marker(Node $marker): void
    {
        $form = $this->lowering->productions->form($marker);
        if ($form->signature !== 'scanpt:') {
            throw ImplementationGap::production($form);
        }
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
     * Tells whether an `as` introduces its alias with the keyword AS, or has no alias.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $alias): bool
    {
        $form = $this->lowering->productions->form($alias);

        return match ($form->signature) {
            'as:', 'as: AS nm' => true,
            'as: ids' => false,
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
