<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers DO, CALL, IMPORT TABLE and the prepared statement commands.
 *
 * Rule: MYSQL-INVOCATION-LOWERING-001. Scope: do, do_stmt, call,
 * opt_sp_cparam_list, opt_sp_cparams, sp_cparams, call_stmt,
 * opt_paren_expr_list, import_stmt, prepare, prepare_src, execute,
 * execute_using, execute_var_list, execute_var_ident, deallocate,
 * deallocate_or_drop. The expressions of DO in MySQL 5.6 are select items
 * without aliases. The parentheses of an empty argument list of CALL are
 * noise (DmlNoise). Terminates: the parts are strict subtrees; lists are
 * flattened by MYSQL-DML-LIST-001. Source: https://dev.mysql.com/doc/refman/8.4/en/do.html,
 * https://dev.mysql.com/doc/refman/8.4/en/call.html,
 * https://dev.mysql.com/doc/refman/8.4/en/import-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-prepared-statements.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class InvocationRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `do` or `do_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function evaluation(Form $form): Evaluation
    {
        switch ($form->signature) {
            case 'do: DO_SYM expr_list':
                return new Evaluation(array_map(static fn (Scalar $expression): SelectExpression => new SelectExpression($expression), $this->lowering->expressions->expressions($form->node(1))));
            case 'do_stmt: DO_SYM empty_select_options select_item_list':
                $this->lowering->options->skip($form->node(1));

                return new Evaluation($this->lowering->queries->selectItems($form->node(2)));
            case 'do_stmt: DO_SYM select_item_list':
                return new Evaluation($this->lowering->queries->selectItems($form->node(1)));
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers a node of `call` or `call_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function call(Form $form): ProcedureCall
    {
        if ($form->signature !== 'call: CALL_SYM sp_name opt_sp_cparam_list' && $form->signature !== 'call_stmt: CALL_SYM sp_name opt_paren_expr_list') {
            throw ImplementationGap::production($form);
        }

        return new ProcedureCall($this->lowering->names->qualified($form->node(1)), $this->arguments($form->node(2)));
    }

    /**
     * Lowers the arguments of CALL: a node of `opt_sp_cparam_list` or `opt_paren_expr_list`.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function arguments(Node $list): array
    {
        $form = $this->lowering->form($list);
        switch ($form->signature) {
            case 'opt_sp_cparam_list:':
            case 'opt_paren_expr_list:':
                return [];
            case 'opt_paren_expr_list: ( opt_expr_list )':
                return $this->lowering->expressions->expressions($form->node(1));
            case 'opt_sp_cparam_list: ( opt_sp_cparams )':
                $inner = $this->lowering->form($form->node(1));
                if ($inner->signature === 'opt_sp_cparams:') {
                    return [];
                }
                if ($inner->signature !== 'opt_sp_cparams: sp_cparams') {
                    throw ImplementationGap::production($inner);
                }
                $arguments = [];
                foreach ((new ListRule($this->lowering))->items($inner->node(0), ['sp_cparams: sp_cparams , expr', 'sp_cparams: expr']) as $item) {
                    $arguments[] = $this->lowering->expressions->expression($item);
                }

                return $arguments;
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers a node of `import_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function import(Form $form): ImportTable
    {
        if ($form->signature !== 'import_stmt: IMPORT TABLE_SYM FROM TEXT_STRING_sys_list') {
            throw ImplementationGap::production($form);
        }

        return new ImportTable($this->lowering->literals->texts($form->node(3)));
    }

    /**
     * Lowers a node of `prepare`, `execute` or `deallocate`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function prepared(Form $form): Statement
    {
        switch ($form->signature) {
            case 'prepare: PREPARE_SYM ident FROM prepare_src':
                $source = $this->lowering->form($form->node(3));

                return new Prepare($this->lowering->names->identifier($form->node(1)), match ($source->signature) {
                    'prepare_src: TEXT_STRING_sys' => $this->lowering->literals->text($source->node(0)),
                    'prepare_src: @ ident_or_text' => $this->lowering->variables->user($source->node(1)),
                    default => throw ImplementationGap::production($source),
                });
            case 'execute: EXECUTE_SYM ident execute_using':
                return new Execute($this->lowering->names->identifier($form->node(1)), $this->variables($form->node(2)));
            case 'deallocate: deallocate_or_drop PREPARE_SYM ident':
                $verb = $this->lowering->form($form->node(0));
                if ($verb->signature !== 'deallocate_or_drop: DEALLOCATE_SYM' && $verb->signature !== 'deallocate_or_drop: DROP') {
                    throw ImplementationGap::production($verb);
                }

                return new Deallocate($this->lowering->names->identifier($form->node(2)));
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the variables of EXECUTE: a node of `execute_using`; an absent USING is empty.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable>
     * @throws ImplementationGap When a production has no rule
     */
    public function variables(Node $using): array
    {
        $form = $this->lowering->form($using);
        if ($form->signature === 'execute_using:') {
            return [];
        }
        if ($form->signature !== 'execute_using: USING execute_var_list') {
            throw ImplementationGap::production($form);
        }
        $variables = [];
        foreach ((new ListRule($this->lowering))->items($form->node(1), ['execute_var_list: execute_var_list , execute_var_ident', 'execute_var_list: execute_var_ident']) as $item) {
            $variable = $this->lowering->form($item);
            if ($variable->signature !== 'execute_var_ident: @ ident_or_text') {
                throw ImplementationGap::production($variable);
            }
            $variables[] = $this->lowering->variables->user($variable->node(1));
        }

        return $variables;
    }
}
