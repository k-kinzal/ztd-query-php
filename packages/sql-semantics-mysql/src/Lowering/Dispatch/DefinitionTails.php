<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dispatch;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Statement;

/**
 * Splits `CREATE` of a view, trigger, stored routine, loadable function or event by the kind of object.
 *
 * Rule: MYSQL-DEFINITION-TAILS-001. Scope: view_or_trigger_or_sp_or_event,
 * definer_tail, no_definer_tail. The DEFINER clause is lowered here and
 * handed, with the tail that names the object, to the family of that
 * object: a view to the table definition family, everything else to the
 * stored program family. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionTails
{
    /**
     * The tail productions, by whether the object is a view.
     */
    private const TAILS = [
        'definer_tail: view_tail' => true, 'definer_tail: trigger_tail' => false, 'definer_tail: sp_tail' => false, 'definer_tail: sf_tail' => false,
        'definer_tail: event_tail' => false, 'no_definer_tail: view_tail' => true, 'no_definer_tail: trigger_tail' => false,
        'no_definer_tail: sp_tail' => false, 'no_definer_tail: sf_tail' => false, 'no_definer_tail: udf_tail' => false, 'no_definer_tail: event_tail' => false,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the object definition that follows CREATE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function create(Node $definition): Statement
    {
        $form = $this->lowering->productions->form($definition);
        $last = count($form->node->children) - 1;

        return match ($form->signature) {
            'view_or_trigger_or_sp_or_event: definer definer_tail', 'view_or_trigger_or_sp_or_event: definer init_lex_create_info definer_tail' => $this->tail($form->node($last), $this->lowering->users->definer($form->node(0))),
            'view_or_trigger_or_sp_or_event: no_definer no_definer_tail', 'view_or_trigger_or_sp_or_event: no_definer init_lex_create_info no_definer_tail' => $this->tail($form->node($last), null),
            'view_or_trigger_or_sp_or_event: view_replace_or_algorithm definer_opt view_tail',
            'view_or_trigger_or_sp_or_event: view_replace_or_algorithm definer_opt init_lex_create_info view_tail' => $this->lowering->tableDefinitions->createView($form->node($last), $form->node(0), $this->lowering->users->definer($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Hands the tail of a definition to the family of the object it names.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tail(Node $tail, ?Account $definer): Statement
    {
        $form = $this->lowering->productions->form($tail);
        $view = self::TAILS[$form->signature] ?? throw ImplementationGap::production($form);

        return $view ? $this->lowering->tableDefinitions->createView($form->node(0), null, $definer) : $this->lowering->routines->create($form->node(0), $definer);
    }
}
