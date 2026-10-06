<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AtomicBody;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultTable;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\RoutineParameters;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE FUNCTION and CREATE PROCEDURE with their parameters, results and bodies.
 *
 * Rule: PG-FUNCTION-LOWER-001. Scope: `CreateFunctionStmt`,
 * `func_args_with_defaults`, `func_args_with_defaults_list`,
 * `func_arg_with_default`, `func_return`, `table_func_column`,
 * `table_func_column_list`, `opt_routine_body`, `ReturnStmt`,
 * `routine_body_stmt_list`, `routine_body_stmt`. Constructors:
 * `CreateFunction`, `RoutineParameters`, `ResultTable`, `ResultColumn`,
 * `ReturnStatement`, `AtomicBody`. The statements of a BEGIN ATOMIC block are
 * lowered by their own families; an empty statement between semicolons is
 * discarded, as the server does. Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html, https://www.postgresql.org/docs/17/sql-createprocedure.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class FunctionRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateFunctionStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function create(Node $statement): CreateFunction
    {
        $form = $this->lowering->productions->form($statement);
        $replace = $this->lowering->flags->present($form->node(1));
        $name = $this->lowering->names->dotted($form->node(3));
        $parameters = $this->parameters($form->node(4));
        $options = new OptionRule($this->lowering);

        return match ($form->signature) {
            'CreateFunctionStmt: CREATE opt_or_replace FUNCTION func_name func_args_with_defaults RETURNS func_return opt_createfunc_opt_list opt_routine_body' => new CreateFunction($name, $parameters, $this->result($form->node(6)), $options->options($form->node(7)), $this->body($form->node(8)), false, $replace),
            'CreateFunctionStmt: CREATE opt_or_replace FUNCTION func_name func_args_with_defaults RETURNS TABLE ( table_func_column_list ) opt_createfunc_opt_list opt_routine_body' => new CreateFunction($name, $parameters, $this->table($form->node(8)), $options->options($form->node(10)), $this->body($form->node(11)), false, $replace),
            'CreateFunctionStmt: CREATE opt_or_replace FUNCTION func_name func_args_with_defaults opt_createfunc_opt_list opt_routine_body' => new CreateFunction($name, $parameters, null, $options->options($form->node(5)), $this->body($form->node(6)), false, $replace),
            'CreateFunctionStmt: CREATE opt_or_replace PROCEDURE func_name func_args_with_defaults opt_createfunc_opt_list opt_routine_body' => new CreateFunction($name, $parameters, null, $options->options($form->node(5)), $this->body($form->node(6)), true, $replace),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `func_args_with_defaults`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parameters(Node $parameters): RoutineParameters
    {
        $form = $this->lowering->productions->form($parameters);
        if ($form->signature === 'func_args_with_defaults: ( )') {
            return new RoutineParameters([]);
        }
        if ($form->signature !== 'func_args_with_defaults: ( func_args_with_defaults_list )') {
            throw ImplementationGap::production($form);
        }
        $list = [];
        foreach ($this->lowering->items($form->node(1), 'func_args_with_defaults_list: func_arg_with_default', 'func_args_with_defaults_list: func_args_with_defaults_list , func_arg_with_default') as $parameter) {
            $list[] = $this->parameter($parameter);
        }

        return new RoutineParameters($list);
    }

    /**
     * Lowers `func_arg_with_default`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parameter(Node $parameter): FunctionParameter
    {
        $form = $this->lowering->productions->form($parameter);
        $signatures = new SignatureRule($this->lowering);

        return match ($form->signature) {
            'func_arg_with_default: func_arg' => $signatures->parameter($form->node(0)),
            'func_arg_with_default: func_arg DEFAULT a_expr' => $signatures->parameter($form->node(0), $this->lowering->expressions->expression($form->node(2)), DefaultSpelling::Keyword),
            'func_arg_with_default: func_arg = a_expr' => $signatures->parameter($form->node(0), $this->lowering->expressions->expression($form->node(2)), DefaultSpelling::EqualsSign),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `func_return`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function result(Node $result): TypeName
    {
        $form = $this->lowering->productions->form($result);
        if ($form->signature !== 'func_return: func_type') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->types->functionType($form->node(0));
    }

    /**
     * Lowers `table_func_column_list`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function table(Node $list): ResultTable
    {
        $columns = [];
        foreach ($this->lowering->items($list, 'table_func_column_list: table_func_column', 'table_func_column_list: table_func_column_list , table_func_column') as $column) {
            $form = $this->lowering->productions->form($column);
            if ($form->signature !== 'table_func_column: param_name func_type') {
                throw ImplementationGap::production($form);
            }
            $columns[] = new ResultColumn($this->lowering->names->name($form->node(0)), $this->lowering->types->functionType($form->node(1)));
        }

        return new ResultTable($columns);
    }

    /**
     * Lowers `opt_routine_body`; no body is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function body(Node $body): ReturnStatement|AtomicBody|null
    {
        $form = $this->lowering->productions->form($body);

        return match ($form->signature) {
            'opt_routine_body:' => null,
            'opt_routine_body: ReturnStmt' => $this->return($form->node(0)),
            'opt_routine_body: BEGIN_P ATOMIC routine_body_stmt_list END_P' => new AtomicBody($this->statements($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ReturnStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function return(Node $statement): ReturnStatement
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'ReturnStmt: RETURN a_expr') {
            throw ImplementationGap::production($form);
        }

        return new ReturnStatement($this->lowering->expressions->expression($form->node(1)));
    }

    /**
     * Lowers `routine_body_stmt_list`, dropping empty statements.
     *
     * @return list<Statement|ReturnStatement>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statements(Node $list): array
    {
        $statements = [];
        foreach ($this->lowering->items($list, 'routine_body_stmt_list: routine_body_stmt_list routine_body_stmt ;', 'routine_body_stmt_list:') as $item) {
            $form = $this->lowering->productions->form($item);
            $statement = match ($form->signature) {
                'routine_body_stmt: stmt' => $this->lowering->optional($form->node(0)),
                'routine_body_stmt: ReturnStmt' => $this->return($form->node(0)),
                default => throw ImplementationGap::production($form),
            };
            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
