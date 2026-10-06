<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\StatementRule;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;

/**
 * Lowers CREATE TRIGGER.
 *
 * Rule: MYSQL-TRIGGER-LOWERING-001. Scope: trigger_tail, trg_action_time,
 * trg_event, trigger_follows_precedes_clause, trigger_action_order. The
 * symbols of the tail are read by rule name, so one reading serves the
 * layouts of every release. Constructs: CreateTrigger, TriggerTable,
 * TriggerOrder. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TriggerRule
{
    /**
     * The trigger definition productions.
     */
    private const TAILS = [
        'trigger_tail: TRIGGER_SYM remember_name sp_name trg_action_time trg_event ON remember_name table_ident FOR_SYM remember_name EACH_SYM ROW_SYM sp_proc_stmt' => true,
        'trigger_tail: TRIGGER_SYM sp_name trg_action_time trg_event ON table_ident FOR_SYM EACH_SYM ROW_SYM trigger_follows_precedes_clause sp_proc_stmt' => true,
        'trigger_tail: TRIGGER_SYM opt_if_not_exists sp_name trg_action_time trg_event ON_SYM table_ident FOR_SYM EACH_SYM ROW_SYM trigger_follows_precedes_clause sp_proc_stmt' => true,
    ];

    /**
     * The action times.
     */
    private const TIMES = ['trg_action_time: BEFORE_SYM' => TriggerTime::Before, 'trg_action_time: AFTER_SYM' => TriggerTime::After];

    /**
     * The triggering events.
     */
    private const EVENTS = [
        'trg_event: INSERT' => TriggerEvent::Insert, 'trg_event: INSERT_SYM' => TriggerEvent::Insert, 'trg_event: UPDATE_SYM' => TriggerEvent::Update,
        'trg_event: DELETE_SYM' => TriggerEvent::Delete,
    ];

    /**
     * The order keywords.
     */
    private const PLACEMENTS = ['trigger_action_order: FOLLOWS_SYM' => OrderPlacement::Follows, 'trigger_action_order: PRECEDES_SYM' => OrderPlacement::Precedes];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE TRIGGER: a node of `trigger_tail`, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $tail, ?Account $definer): CreateTrigger
    {
        $form = $this->lowering->form($tail);
        if (!isset(self::TAILS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $parts = [];
        foreach ($form->node->children as $child) {
            if ($child instanceof Node && $child->name === 'remember_name') {
                $this->lowering->options->skip($child);
            } elseif ($child instanceof Node) {
                $parts[$child->name] = $child;
            }
        }
        $time = $this->lowering->form($parts['trg_action_time']);
        $event = $this->lowering->form($parts['trg_event']);

        return new CreateTrigger(
            $this->lowering->names->qualified($parts['sp_name']),
            self::TIMES[$time->signature] ?? throw ImplementationGap::production($time),
            self::EVENTS[$event->signature] ?? throw ImplementationGap::production($event),
            new TriggerTable($this->lowering->names->qualified($parts['table_ident'])),
            (new StatementRule($this->lowering))->statement($parts['sp_proc_stmt']),
            isset($parts['trigger_follows_precedes_clause']) ? $this->order($parts['trigger_follows_precedes_clause']) : null,
            $definer,
            isset($parts['opt_if_not_exists']) && $this->lowering->options->present($parts['opt_if_not_exists']),
        );
    }

    /**
     * Lowers the FOLLOWS or PRECEDES clause: a node of `trigger_follows_precedes_clause`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function order(Node $clause): ?TriggerOrder
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'trigger_follows_precedes_clause:') {
            return null;
        }
        if ($form->signature !== 'trigger_follows_precedes_clause: trigger_action_order ident_or_text') {
            throw ImplementationGap::production($form);
        }
        $placement = $this->lowering->form($form->node(0));

        return new TriggerOrder(self::PLACEMENTS[$placement->signature] ?? throw ImplementationGap::production($placement), $this->lowering->names->identifier($form->node(1)));
    }
}
