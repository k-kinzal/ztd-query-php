<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\ConditionRule;
use SqlSemantics\Platform\MySql\Lowering\Routine\ParameterRule;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers BEGIN ... END blocks, their declarations and the cursor statements.
 *
 * Rule: MYSQL-PROGRAM-BLOCK-LOWERING-001. Scope: sp_labeled_block,
 * sp_unlabeled_block, sp_block_content, sp_decls, sp_decl, sp_decl_idents,
 * sp_opt_default, sp_handler_type, sp_proc_stmt_open, sp_proc_stmt_fetch,
 * sp_proc_stmt_close, sp_opt_fetch_noise, sp_fetch_list. The words NEXT
 * FROM and FROM of FETCH hold no operand. Constructs: Block,
 * VariableDeclaration, ConditionDeclaration, CursorDeclaration,
 * HandlerDeclaration, OpenCursor, FetchCursor, CloseCursor. Terminates:
 * every child is a strict subtree; lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/begin-end.html,
 * https://dev.mysql.com/doc/refman/8.4/en/declare.html,
 * https://dev.mysql.com/doc/refman/8.4/en/cursors.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BlockRule
{
    /**
     * The handler actions.
     */
    private const ACTIONS = ['sp_handler_type: EXIT_SYM' => HandlerAction::Exit, 'sp_handler_type: CONTINUE_SYM' => HandlerAction::Continue];

    /**
     * The optional words of FETCH, which hold no operand.
     */
    private const FETCH_WORDS = ['sp_opt_fetch_noise:' => true, 'sp_opt_fetch_noise: NEXT_SYM FROM' => true, 'sp_opt_fetch_noise: FROM' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a block: the form of `sp_labeled_block` or `sp_unlabeled_block`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function block(Form $form): Block
    {
        [$content, $label, $end] = match ($form->signature) {
            'sp_unlabeled_block: sp_block_content' => [$form->node(0), null, null],
            'sp_labeled_block: label_ident : sp_block_content sp_opt_label' => [$form->node(2), $this->lowering->names->identifier($form->node(0)), (new FlowRule($this->lowering))->endLabel($form->node(3))],
            default => throw ImplementationGap::production($form),
        };
        $body = $this->lowering->form($content);
        if ($body->signature !== 'sp_block_content: BEGIN_SYM sp_decls sp_proc_stmts END') {
            throw ImplementationGap::production($body);
        }
        $declarations = [];
        foreach ((new Sequence($this->lowering))->items($body->node(1), ['sp_decls:', 'sp_decls: sp_decls sp_decl ;']) as $item) {
            $declarations[] = $this->declaration($item);
        }

        return new Block($declarations, (new StatementRule($this->lowering))->statements($body->node(2)), $label, $end);
    }

    /**
     * Lowers one DECLARE: a node of `sp_decl`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function declaration(Node $declaration): Declaration
    {
        $form = $this->lowering->form($declaration);
        $parameters = new ParameterRule($this->lowering);
        switch ($form->signature) {
            case 'sp_decl: DECLARE_SYM sp_decl_idents type_with_opt_collate sp_opt_default':
                [$type, $collation] = $parameters->declared($form->node(2), null);

                return new VariableDeclaration($this->names($form->node(1), 'sp_decl_idents'), $type, $collation, $this->initial($form->node(3)));
            case 'sp_decl: DECLARE_SYM sp_decl_idents type opt_collate sp_opt_default':
                [$type, $collation] = $parameters->declared($form->node(2), $form->node(3));

                return new VariableDeclaration($this->names($form->node(1), 'sp_decl_idents'), $type, $collation, $this->initial($form->node(4)));
            case 'sp_decl: DECLARE_SYM ident CONDITION_SYM FOR_SYM sp_cond':
                return new ConditionDeclaration($this->lowering->names->identifier($form->node(1)), (new ConditionRule($this->lowering))->value($form->node(4)));
            case 'sp_decl: DECLARE_SYM sp_handler_type HANDLER_SYM FOR_SYM sp_hcond_list sp_proc_stmt':
                $action = $this->lowering->form($form->node(1));

                return new HandlerDeclaration(
                    self::ACTIONS[$action->signature] ?? throw ImplementationGap::production($action),
                    (new ConditionRule($this->lowering))->handled($form->node(4)),
                    (new StatementRule($this->lowering))->statement($form->node(5)),
                );
            case 'sp_decl: DECLARE_SYM ident CURSOR_SYM FOR_SYM select':
            case 'sp_decl: DECLARE_SYM ident CURSOR_SYM FOR_SYM select_stmt':
                return new CursorDeclaration($this->lowering->names->identifier($form->node(1)), $this->lowering->queries->query($form->node(4)));
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers a comma-separated list of names: a node of `sp_decl_idents` or `sp_fetch_list`.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function names(Node $list, string $rule): array
    {
        $names = [];
        foreach ((new Sequence($this->lowering))->items($list, $rule === 'sp_decl_idents' ? ['sp_decl_idents: ident', 'sp_decl_idents: sp_decl_idents , ident'] : ['sp_fetch_list: ident', 'sp_fetch_list: sp_fetch_list , ident']) as $item) {
            $names[] = $this->lowering->names->identifier($item);
        }

        return $names;
    }

    /**
     * Lowers the DEFAULT clause of a variable declaration: a node of `sp_opt_default`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function initial(Node $default): ?Scalar
    {
        $form = $this->lowering->form($default);

        return match ($form->signature) {
            'sp_opt_default:' => null,
            'sp_opt_default: DEFAULT expr', 'sp_opt_default: DEFAULT_SYM expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers OPEN, FETCH or CLOSE: the form of `sp_proc_stmt_open`, `sp_proc_stmt_fetch` or `sp_proc_stmt_close`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function cursor(Form $form): OpenCursor|FetchCursor|CloseCursor
    {
        if ($form->signature === 'sp_proc_stmt_fetch: FETCH_SYM sp_opt_fetch_noise ident INTO sp_fetch_list') {
            $words = $this->lowering->form($form->node(1));
            if (!isset(self::FETCH_WORDS[$words->signature])) {
                throw ImplementationGap::production($words);
            }

            return new FetchCursor($this->lowering->names->identifier($form->node(2)), $this->names($form->node(4), 'sp_fetch_list'));
        }

        return match ($form->signature) {
            'sp_proc_stmt_open: OPEN_SYM ident' => new OpenCursor($this->lowering->names->identifier($form->node(1))),
            'sp_proc_stmt_close: CLOSE_SYM ident' => new CloseCursor($this->lowering->names->identifier($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }
}
