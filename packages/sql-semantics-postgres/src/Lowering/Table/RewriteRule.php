<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE RULE.
 *
 * Rule: PG-REWRITE-RULE-LOWER-001. Scope: `RuleStmt`, `RuleActionList`,
 * `RuleActionMulti`, `RuleActionStmt`, `RuleActionStmtOrEmpty`, `event`,
 * `opt_instead`. An empty statement between semicolons is dropped, as the
 * server drops it; the semicolons are noise (TableNoise). Termination: lists
 * are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-createrule.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RewriteRule
{
    /**
     * The command each `event` production names.
     */
    private const EVENTS = ['event: SELECT' => RuleEvent::Select, 'event: UPDATE' => RuleEvent::Update, 'event: DELETE_P' => RuleEvent::Delete, 'event: INSERT' => RuleEvent::Insert];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `RuleStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function rule(Node $statement): CreateRule
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'RuleStmt: CREATE opt_or_replace RULE name AS ON event TO qualified_name where_clause DO opt_instead RuleActionList') {
            throw ImplementationGap::production($form);
        }
        $event = $this->lowering->productions->form($form->node(6));
        $instead = $this->lowering->productions->form($form->node(11));
        [$actions, $grouped] = $this->actions($form->node(12));

        return new CreateRule(
            $this->lowering->names->name($form->node(3)),
            self::EVENTS[$event->signature] ?? throw ImplementationGap::production($event),
            $this->lowering->names->qualified($form->node(8)),
            $actions,
            $grouped,
            match ($instead->signature) {
                'opt_instead: INSTEAD' => true,
                'opt_instead: ALSO' => false,
                'opt_instead:' => null,
                default => throw ImplementationGap::production($instead),
            },
            $this->lowering->queries->where($form->node(9)),
            $this->lowering->flags->present($form->node(1)),
        );
    }

    /**
     * Lowers `RuleActionList`: the actions and whether they are parenthesized.
     *
     * @return array{list<Statement>, bool}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function actions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'RuleActionList: NOTHING') {
            return [[], false];
        }
        if ($form->signature === 'RuleActionList: RuleActionStmt') {
            return [[$this->action($form->node(0))], false];
        }
        if ($form->signature !== 'RuleActionList: ( RuleActionMulti )') {
            throw ImplementationGap::production($form);
        }
        $actions = [];
        foreach ($this->lowering->items($form->node(1), 'RuleActionMulti: RuleActionMulti ; RuleActionStmtOrEmpty', 'RuleActionMulti: RuleActionStmtOrEmpty') as $item) {
            $optional = $this->lowering->productions->form($item);
            if ($optional->signature === 'RuleActionStmtOrEmpty: RuleActionStmt') {
                $actions[] = $this->action($optional->node(0));
            } elseif ($optional->signature !== 'RuleActionStmtOrEmpty:') {
                throw ImplementationGap::production($optional);
            }
        }

        return [$actions, true];
    }

    /**
     * Lowers `RuleActionStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function action(Node $action): Statement
    {
        $form = $this->lowering->productions->form($action);

        return match ($form->signature) {
            'RuleActionStmt: SelectStmt' => $this->lowering->queries->query($form->node(0)),
            'RuleActionStmt: InsertStmt', 'RuleActionStmt: UpdateStmt', 'RuleActionStmt: DeleteStmt' => $this->lowering->manipulations->statement($form->node(0)),
            'RuleActionStmt: NotifyStmt' => $this->lowering->utilities->statement($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
