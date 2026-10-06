<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\DiagnosticsRule;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\SignalRule;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the routine family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-ROUTINE-ENTRY-001. Scope: stored procedures, functions, triggers, events, loadable functions and
 * compound statements.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family.
 * Terminates: constant dispatch.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-compound-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoutineRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a stored program statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);

        return match ($statement->name) {
            'signal_stmt', 'resignal_stmt' => (new SignalRule($this->lowering))->statement($form),
            'get_diagnostics' => (new DiagnosticsRule($this->lowering))->statement($form),
            'alter_procedure_stmt', 'alter_function_stmt', 'alter_event_stmt', 'drop_procedure_stmt', 'drop_function_stmt', 'drop_trigger_stmt', 'drop_event_stmt' => $this->definition($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a production of `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this family.
     *
     * The form may also be that of one of the 8.0 statement rules `alter_procedure_stmt`,
     * `alter_function_stmt`, `alter_event_stmt` and `drop_*_stmt` of stored programs.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Form $form): Statement
    {
        $symbols = explode(' ', $form->signature);

        return match (true) {
            in_array('EVENT_SYM', $symbols, true) && $symbols[1] === 'ALTER' => (new EventRule($this->lowering))->alter($form),
            $symbols[1] === 'ALTER' => (new DefinitionRule($this->lowering))->alter($form),
            $symbols[1] === 'DROP' => (new DefinitionRule($this->lowering))->drop($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE of a stored program: a node of `trigger_tail`, `sp_tail`, `sf_tail`, `event_tail` or
     * `udf_tail`, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $tail, ?Account $definer): Statement
    {
        return match ($tail->name) {
            'sp_tail', 'sf_tail' => (new DefinitionRule($this->lowering))->routine($tail, $definer),
            'udf_tail' => (new DefinitionRule($this->lowering))->loadable($tail),
            'trigger_tail' => (new TriggerRule($this->lowering))->create($tail, $definer),
            'event_tail' => (new EventRule($this->lowering))->create($tail, $definer),
            default => throw ImplementationGap::production($this->lowering->form($tail)),
        };
    }
}
