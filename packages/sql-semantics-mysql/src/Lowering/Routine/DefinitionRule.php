<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\StatementRule;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers CREATE, ALTER and DROP of stored procedures and functions, CREATE of loadable functions, and DROP of triggers and events.
 *
 * Rule: MYSQL-ROUTINE-DEFINITION-LOWERING-001. Scope: sp_tail, sf_tail,
 * udf_tail, udf_type, alter_procedure_stmt, alter_function_stmt,
 * drop_procedure_stmt, drop_function_stmt, drop_trigger_stmt,
 * drop_event_stmt, and the `alter` and `drop` alternatives of these
 * objects (5.6, 5.7). The symbols of a tail are read by rule name, so one
 * reading serves the layouts of every release. Constructs: CreateProcedure,
 * CreateFunction, CreateLoadableFunction, AlterRoutine, DropProgram.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-function-loadable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-procedure.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionRule
{
    /**
     * The productions of stored procedure and function definitions.
     */
    private const ROUTINES = [
        'sp_tail: PROCEDURE_SYM remember_name sp_name ( sp_pdparam_list ) sp_c_chistics sp_proc_stmt' => true,
        'sp_tail: PROCEDURE_SYM sp_name ( sp_pdparam_list ) sp_c_chistics sp_proc_stmt' => true,
        'sp_tail: PROCEDURE_SYM opt_if_not_exists sp_name ( sp_pdparam_list ) sp_c_chistics sp_proc_stmt' => true,
        'sp_tail: PROCEDURE_SYM opt_if_not_exists sp_name ( sp_pdparam_list ) sp_c_chistics stored_routine_body' => true,
        'sf_tail: remember_name FUNCTION_SYM sp_name ( sp_fdparam_list ) RETURNS_SYM type_with_opt_collate sp_c_chistics sp_proc_stmt' => true,
        'sf_tail: FUNCTION_SYM sp_name ( sp_fdparam_list ) RETURNS_SYM type_with_opt_collate sp_c_chistics sp_proc_stmt' => true,
        'sf_tail: FUNCTION_SYM opt_if_not_exists sp_name ( sp_fdparam_list ) RETURNS_SYM type opt_collate sp_c_chistics sp_proc_stmt' => true,
        'sf_tail: FUNCTION_SYM opt_if_not_exists sp_name ( sp_fdparam_list ) RETURNS_SYM type opt_collate sp_c_chistics stored_routine_body' => true,
    ];

    /**
     * The productions of loadable function definitions, and whether each defines an aggregate function.
     */
    private const LOADABLE = [
        'udf_tail: AGGREGATE_SYM remember_name FUNCTION_SYM ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => true,
        'udf_tail: remember_name FUNCTION_SYM ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => false,
        'udf_tail: AGGREGATE_SYM FUNCTION_SYM ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => true,
        'udf_tail: FUNCTION_SYM ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => false,
        'udf_tail: AGGREGATE_SYM FUNCTION_SYM opt_if_not_exists ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => true,
        'udf_tail: FUNCTION_SYM opt_if_not_exists ident RETURNS_SYM udf_type SONAME_SYM TEXT_STRING_sys' => false,
    ];

    /**
     * The return types of loadable functions.
     */
    private const RESULTS = [
        'udf_type: STRING_SYM' => LoadableResult::String, 'udf_type: REAL' => LoadableResult::Real, 'udf_type: REAL_SYM' => LoadableResult::Real,
        'udf_type: DECIMAL_SYM' => LoadableResult::Decimal, 'udf_type: INT_SYM' => LoadableResult::Integer,
    ];

    /**
     * The ALTER productions, by the kind of routine.
     */
    private const ALTERS = [
        'alter: ALTER PROCEDURE_SYM sp_name sp_a_chistics' => ProgramKind::Procedure, 'alter: ALTER FUNCTION_SYM sp_name sp_a_chistics' => ProgramKind::Function,
        'alter_procedure_stmt: ALTER PROCEDURE_SYM sp_name sp_a_chistics' => ProgramKind::Procedure,
        'alter_function_stmt: ALTER FUNCTION_SYM sp_name sp_a_chistics' => ProgramKind::Function,
    ];

    /**
     * The DROP productions that name the program with `sp_name`, by the kind of program.
     */
    private const DROPS = [
        'drop: DROP PROCEDURE_SYM if_exists sp_name' => ProgramKind::Procedure, 'drop: DROP EVENT_SYM if_exists sp_name' => ProgramKind::Event,
        'drop: DROP TRIGGER_SYM if_exists sp_name' => ProgramKind::Trigger, 'drop_procedure_stmt: DROP PROCEDURE_SYM if_exists sp_name' => ProgramKind::Procedure,
        'drop_event_stmt: DROP EVENT_SYM if_exists sp_name' => ProgramKind::Event, 'drop_trigger_stmt: DROP TRIGGER_SYM if_exists sp_name' => ProgramKind::Trigger,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE PROCEDURE or CREATE FUNCTION of a stored function: a node of `sp_tail` or `sf_tail`, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function routine(Node $tail, ?Account $definer): CreateProcedure|CreateFunction
    {
        $form = $this->lowering->form($tail);
        if (!isset(self::ROUTINES[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $parts = [];
        foreach ($form->node->children as $child) {
            if ($child instanceof Node) {
                $parts[$child->name] = $child;
            }
        }
        if (isset($parts['remember_name'])) {
            $this->lowering->options->skip($parts['remember_name']);
        }
        $exists = isset($parts['opt_if_not_exists']) && $this->lowering->options->present($parts['opt_if_not_exists']);
        $name = $this->lowering->names->qualified($parts['sp_name']);
        $characteristics = (new CharacteristicRule($this->lowering))->characteristics($parts['sp_c_chistics']);
        $body = (new StatementRule($this->lowering))->body($parts['stored_routine_body'] ?? $parts['sp_proc_stmt']);
        if ($tail->name === 'sp_tail') {
            return new CreateProcedure($name, (new ParameterRule($this->lowering))->parameters($parts['sp_pdparam_list']), $body, $characteristics, $definer, $exists);
        }
        [$type, $collation] = (new ParameterRule($this->lowering))->declared($parts['type_with_opt_collate'] ?? $parts['type'], $parts['opt_collate'] ?? null);

        return new CreateFunction($name, (new ParameterRule($this->lowering))->parameters($parts['sp_fdparam_list']), $type, $body, $collation, $characteristics, $definer, $exists);
    }

    /**
     * Lowers CREATE FUNCTION of a loadable function: a node of `udf_tail`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function loadable(Node $tail): CreateLoadableFunction
    {
        $form = $this->lowering->form($tail);
        $aggregate = self::LOADABLE[$form->signature] ?? throw ImplementationGap::production($form);
        $count = count($form->node->children);
        $exists = false;
        foreach ($form->node->children as $child) {
            if ($child instanceof Node && $child->name === 'remember_name') {
                $this->lowering->options->skip($child);
            }
            if ($child instanceof Node && $child->name === 'opt_if_not_exists') {
                $exists = $this->lowering->options->present($child);
            }
        }
        $result = $this->lowering->form($form->node($count - 3));

        return new CreateLoadableFunction(
            $this->lowering->names->identifier($form->node($count - 5)),
            self::RESULTS[$result->signature] ?? throw ImplementationGap::production($result),
            $this->lowering->literals->text($form->node($count - 1)),
            $aggregate,
            $exists,
        );
    }

    /**
     * Lowers ALTER PROCEDURE or ALTER FUNCTION: the form of a 5.x `alter` alternative, of
     * `alter_procedure_stmt` or of `alter_function_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alter(Form $form): AlterRoutine
    {
        $kind = self::ALTERS[$form->signature] ?? throw ImplementationGap::production($form);

        return new AlterRoutine($kind, $this->lowering->names->qualified($form->node(2)), (new CharacteristicRule($this->lowering))->characteristics($form->node(3)));
    }

    /**
     * Lowers DROP of a procedure, function, trigger or event: the form of a 5.x `drop` alternative or of
     * one of the `drop_*_stmt` rules.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function drop(Form $form): DropProgram
    {
        if (isset(self::DROPS[$form->signature])) {
            return new DropProgram(self::DROPS[$form->signature], $this->lowering->names->qualified($form->node(3)), $this->lowering->options->present($form->node(2)));
        }
        $name = match ($form->signature) {
            'drop: DROP FUNCTION_SYM if_exists ident . ident', 'drop_function_stmt: DROP FUNCTION_SYM if_exists ident . ident' => new QualifiedName($this->lowering->names->identifier($form->node(5)), $this->lowering->names->identifier($form->node(3))),
            'drop: DROP FUNCTION_SYM if_exists ident', 'drop_function_stmt: DROP FUNCTION_SYM if_exists ident' => new QualifiedName($this->lowering->names->identifier($form->node(3))),
            default => throw ImplementationGap::production($form),
        };

        return new DropProgram(ProgramKind::Function, $name, $this->lowering->options->present($form->node(2)));
    }
}
