<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Iterate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the flow control statements of stored programs.
 *
 * Rule: MYSQL-PROGRAM-FLOW-LOWERING-001. Scope: sp_proc_stmt_if, sp_if,
 * sp_elseifs, case_stmt_specification, simple_case_stmt,
 * searched_case_stmt, simple_when_clause_list, searched_when_clause_list,
 * simple_when_clause, searched_when_clause, else_clause_opt,
 * sp_labeled_control, sp_unlabeled_control, sp_opt_label,
 * sp_proc_stmt_return, sp_proc_stmt_leave, sp_proc_stmt_iterate. The chain
 * of ELSEIF rules is followed with a loop. Constructs: IfStatement,
 * SimpleCase, SearchedCase, ConditionalBranch, Loop, WhileLoop, RepeatLoop,
 * Leave, Iterate, ReturnStatement. Terminates: every child is a strict
 * subtree; the ELSEIF chain and the lists are walked iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FlowRule
{
    /**
     * The WHEN list productions.
     */
    private const WHENS = [
        'simple_when_clause_list: simple_when_clause', 'simple_when_clause_list: simple_when_clause_list simple_when_clause',
        'searched_when_clause_list: searched_when_clause', 'searched_when_clause_list: searched_when_clause_list searched_when_clause',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers IF: the form of `sp_proc_stmt_if`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function conditional(Form $form): IfStatement
    {
        if ($form->signature !== 'sp_proc_stmt_if: IF sp_if END IF') {
            throw ImplementationGap::production($form);
        }
        $statements = new StatementRule($this->lowering);
        $branches = [];
        $otherwise = [];
        $branch = $this->lowering->form($form->node(1));
        while ($branch !== null) {
            if ($branch->signature !== 'sp_if: expr THEN_SYM sp_proc_stmts1 sp_elseifs') {
                throw ImplementationGap::production($branch);
            }
            $branches[] = new ConditionalBranch($this->lowering->expressions->expression($branch->node(0)), $statements->statements($branch->node(2)));
            $rest = $this->lowering->form($branch->node(3));
            $branch = match ($rest->signature) {
                'sp_elseifs:', 'sp_elseifs: ELSE sp_proc_stmts1' => null,
                'sp_elseifs: ELSEIF_SYM sp_if' => $this->lowering->form($rest->node(1)),
                default => throw ImplementationGap::production($rest),
            };
            $otherwise = $rest->signature === 'sp_elseifs: ELSE sp_proc_stmts1' ? $statements->statements($rest->node(1)) : [];
        }

        return new IfStatement($branches, $otherwise);
    }

    /**
     * Lowers the CASE statement: the form of `case_stmt_specification`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function choice(Form $form): SimpleCase|SearchedCase
    {
        if ($form->signature !== 'case_stmt_specification: simple_case_stmt' && $form->signature !== 'case_stmt_specification: searched_case_stmt') {
            throw ImplementationGap::production($form);
        }
        $case = $this->lowering->form($form->node(0));
        [$operand, $list, $else] = match ($case->signature) {
            'simple_case_stmt: CASE_SYM expr simple_when_clause_list else_clause_opt END CASE_SYM' => [$this->lowering->expressions->expression($case->node(1)), 2, 3],
            'searched_case_stmt: CASE_SYM searched_when_clause_list else_clause_opt END CASE_SYM' => [null, 1, 2],
            default => throw ImplementationGap::production($case),
        };
        $statements = new StatementRule($this->lowering);
        $branches = [];
        foreach ((new Sequence($this->lowering))->items($case->node($list), self::WHENS) as $item) {
            $when = $this->lowering->form($item);
            if ($when->signature !== 'simple_when_clause: WHEN_SYM expr THEN_SYM sp_proc_stmts1' && $when->signature !== 'searched_when_clause: WHEN_SYM expr THEN_SYM sp_proc_stmts1') {
                throw ImplementationGap::production($when);
            }
            $branches[] = new ConditionalBranch($this->lowering->expressions->expression($when->node(1)), $statements->statements($when->node(3)));
        }
        $rest = $this->lowering->form($case->node($else));
        $otherwise = match ($rest->signature) {
            'else_clause_opt:' => [],
            'else_clause_opt: ELSE sp_proc_stmts1' => $statements->statements($rest->node(1)),
            default => throw ImplementationGap::production($rest),
        };

        return $operand === null ? new SearchedCase($branches, $otherwise) : new SimpleCase($operand, $branches, $otherwise);
    }

    /**
     * Lowers a labeled loop: the form of `sp_labeled_control`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function labeled(Form $form): Loop|WhileLoop|RepeatLoop
    {
        if ($form->signature !== 'sp_labeled_control: label_ident : sp_unlabeled_control sp_opt_label') {
            throw ImplementationGap::production($form);
        }

        return $this->loop($form->node(2), $this->lowering->names->identifier($form->node(0)), $this->endLabel($form->node(3)));
    }

    /**
     * Lowers the optional label after END: a node of `sp_opt_label`; an absent label is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function endLabel(Node $label): ?Name
    {
        $form = $this->lowering->form($label);

        return match ($form->signature) {
            'sp_opt_label:' => null,
            'sp_opt_label: label_ident' => $this->lowering->names->identifier($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers LOOP, WHILE or REPEAT with the labels written around it: a node of `sp_unlabeled_control`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function loop(Node $control, ?Name $label, ?Name $endLabel): Loop|WhileLoop|RepeatLoop
    {
        $form = $this->lowering->form($control);
        $statements = new StatementRule($this->lowering);

        return match ($form->signature) {
            'sp_unlabeled_control: LOOP_SYM sp_proc_stmts1 END LOOP_SYM' => new Loop($statements->statements($form->node(1)), $label, $endLabel),
            'sp_unlabeled_control: WHILE_SYM expr DO_SYM sp_proc_stmts1 END WHILE_SYM' => new WhileLoop($this->lowering->expressions->expression($form->node(1)), $statements->statements($form->node(3)), $label, $endLabel),
            'sp_unlabeled_control: REPEAT_SYM sp_proc_stmts1 UNTIL_SYM expr END REPEAT_SYM' => new RepeatLoop($this->lowering->expressions->expression($form->node(3)), $statements->statements($form->node(1)), $label, $endLabel),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers RETURN, LEAVE or ITERATE: the form of `sp_proc_stmt_return`, `sp_proc_stmt_leave` or `sp_proc_stmt_iterate`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function jump(Form $form): ProgramStatement|Statement
    {
        return match ($form->signature) {
            'sp_proc_stmt_return: RETURN_SYM expr' => new ReturnStatement($this->lowering->expressions->expression($form->node(1))),
            'sp_proc_stmt_leave: LEAVE_SYM label_ident' => new Leave($this->lowering->names->identifier($form->node(1))),
            'sp_proc_stmt_iterate: ITERATE_SYM label_ident' => new Iterate($this->lowering->names->identifier($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }
}
