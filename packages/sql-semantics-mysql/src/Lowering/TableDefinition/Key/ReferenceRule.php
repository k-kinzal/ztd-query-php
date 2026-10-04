<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers REFERENCES clauses.
 *
 * Rule: MYSQL-REFERENCES-LOWERING-001. Scope: references, opt_references,
 * opt_ref_list, ref_list, reference_list, opt_match_clause,
 * opt_on_update_delete, delete_option. The referential actions are kept in
 * written order. Constructs: References, ReferentialAction. Terminates:
 * lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReferenceRule
{
    /**
     * The MATCH clauses.
     */
    private const MATCHES = [
        'opt_match_clause:' => null, 'opt_match_clause: MATCH FULL' => ReferenceMatch::Full, 'opt_match_clause: MATCH PARTIAL' => ReferenceMatch::Partial,
        'opt_match_clause: MATCH SIMPLE_SYM' => ReferenceMatch::Simple,
    ];

    /**
     * The referential action clauses, by the events of their actions in written order.
     */
    private const ACTIONS = [
        'opt_on_update_delete:' => [],
        'opt_on_update_delete: ON UPDATE_SYM delete_option' => [[ReferenceEvent::Update, 2]], 'opt_on_update_delete: ON_SYM UPDATE_SYM delete_option' => [[ReferenceEvent::Update, 2]],
        'opt_on_update_delete: ON DELETE_SYM delete_option' => [[ReferenceEvent::Delete, 2]], 'opt_on_update_delete: ON_SYM DELETE_SYM delete_option' => [[ReferenceEvent::Delete, 2]],
        'opt_on_update_delete: ON UPDATE_SYM delete_option ON DELETE_SYM delete_option' => [[ReferenceEvent::Update, 2], [ReferenceEvent::Delete, 5]],
        'opt_on_update_delete: ON_SYM UPDATE_SYM delete_option ON_SYM DELETE_SYM delete_option' => [[ReferenceEvent::Update, 2], [ReferenceEvent::Delete, 5]],
        'opt_on_update_delete: ON DELETE_SYM delete_option ON UPDATE_SYM delete_option' => [[ReferenceEvent::Delete, 2], [ReferenceEvent::Update, 5]],
        'opt_on_update_delete: ON_SYM DELETE_SYM delete_option ON_SYM UPDATE_SYM delete_option' => [[ReferenceEvent::Delete, 2], [ReferenceEvent::Update, 5]],
    ];

    /**
     * The referential actions.
     */
    private const OPTIONS = [
        'delete_option: RESTRICT' => ReferenceOption::Restrict, 'delete_option: CASCADE' => ReferenceOption::Cascade,
        'delete_option: SET NULL_SYM' => ReferenceOption::SetNull, 'delete_option: SET_SYM NULL_SYM' => ReferenceOption::SetNull,
        'delete_option: NO_SYM ACTION' => ReferenceOption::NoAction, 'delete_option: SET DEFAULT' => ReferenceOption::SetDefault,
        'delete_option: SET_SYM DEFAULT_SYM' => ReferenceOption::SetDefault,
    ];

    /**
     * The column list productions.
     */
    private const COLUMNS = ['ref_list: ref_list , ident', 'ref_list: ident', 'reference_list: reference_list , ident', 'reference_list: ident'];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a REFERENCES clause: a node of `references`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function references(Node $references): References
    {
        $form = $this->lowering->productions->form($references);
        if ($form->signature !== 'references: REFERENCES table_ident opt_ref_list opt_match_clause opt_on_update_delete') {
            throw ImplementationGap::production($form);
        }
        $match = $this->lowering->productions->form($form->node(3));
        if (!array_key_exists($match->signature, self::MATCHES)) {
            throw ImplementationGap::production($match);
        }

        return new References($this->lowering->names->qualified($form->node(1)), $this->columns($form->node(2)), self::MATCHES[$match->signature], $this->actions($form->node(4)));
    }

    /**
     * Lowers an optional REFERENCES clause: a node of `opt_references`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function optional(Node $references): ?References
    {
        $form = $this->lowering->productions->form($references);

        return match ($form->signature) {
            'opt_references:' => null,
            'opt_references: references' => $this->references($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the parent column list: a node of `opt_ref_list`; no list is null.
     *
     * @return list<Name>|null
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): ?array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'opt_ref_list:') {
            return null;
        }
        if ($form->signature !== 'opt_ref_list: ( ref_list )' && $form->signature !== 'opt_ref_list: ( reference_list )') {
            throw ImplementationGap::production($form);
        }
        $columns = [];
        foreach ((new Spine($this->lowering))->items($form->node(1), self::COLUMNS, ['ident']) as $ident) {
            $columns[] = $this->lowering->names->identifier($ident);
        }

        return $columns;
    }

    /**
     * Lowers the referential actions: a node of `opt_on_update_delete`.
     *
     * @return list<ReferentialAction>
     * @throws ImplementationGap When a production has no rule
     */
    public function actions(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        $actions = [];
        foreach (self::ACTIONS[$form->signature] ?? throw ImplementationGap::production($form) as [$event, $position]) {
            $option = $this->lowering->productions->form($form->node($position));
            $actions[] = new ReferentialAction($event, self::OPTIONS[$option->signature] ?? throw ImplementationGap::production($option));
        }

        return $actions;
    }
}
