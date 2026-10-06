<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the statements of stored programs and routes each to its rule.
 *
 * Rule: MYSQL-PROGRAM-STATEMENT-LOWERING-001. Scope: sp_proc_stmt,
 * ev_sql_stmt, ev_sql_stmt_inner, sp_proc_stmt_statement,
 * sp_proc_stmt_unlabeled, sp_proc_stmts, sp_proc_stmts1,
 * stored_routine_body, routine_string. An SQL statement inside a program
 * re-enters the statement dispatcher. The semicolons that end the
 * statements of a list are written back by the statement that holds the
 * list. A dollar-quoted string is split into its tag and its text.
 * Constructs: ExternalBody, DollarQuotedText. Terminates: every statement
 * is a strict subtree; lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-compound-statements.html,
 * https://dev.mysql.com/doc/refman/9.1/en/create-procedure.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StatementRule
{
    /**
     * The alternatives of a program statement; each holds one statement rule.
     */
    private const ALTERNATIVES = [
        'sp_proc_stmt: sp_proc_stmt_statement' => true, 'sp_proc_stmt: sp_proc_stmt_return' => true, 'sp_proc_stmt: sp_proc_stmt_if' => true,
        'sp_proc_stmt: case_stmt_specification' => true, 'sp_proc_stmt: sp_labeled_block' => true, 'sp_proc_stmt: sp_unlabeled_block' => true,
        'sp_proc_stmt: sp_labeled_control' => true, 'sp_proc_stmt: sp_proc_stmt_unlabeled' => true, 'sp_proc_stmt: sp_proc_stmt_leave' => true,
        'sp_proc_stmt: sp_proc_stmt_iterate' => true, 'sp_proc_stmt: sp_proc_stmt_open' => true, 'sp_proc_stmt: sp_proc_stmt_fetch' => true,
        'sp_proc_stmt: sp_proc_stmt_close' => true,
        'ev_sql_stmt_inner: sp_proc_stmt_statement' => true, 'ev_sql_stmt_inner: sp_proc_stmt_return' => true, 'ev_sql_stmt_inner: sp_proc_stmt_if' => true,
        'ev_sql_stmt_inner: case_stmt_specification' => true, 'ev_sql_stmt_inner: sp_labeled_block' => true, 'ev_sql_stmt_inner: sp_unlabeled_block' => true,
        'ev_sql_stmt_inner: sp_labeled_control' => true, 'ev_sql_stmt_inner: sp_proc_stmt_unlabeled' => true, 'ev_sql_stmt_inner: sp_proc_stmt_leave' => true,
        'ev_sql_stmt_inner: sp_proc_stmt_iterate' => true, 'ev_sql_stmt_inner: sp_proc_stmt_open' => true, 'ev_sql_stmt_inner: sp_proc_stmt_fetch' => true,
        'ev_sql_stmt_inner: sp_proc_stmt_close' => true,
    ];

    /**
     * The statement list productions.
     */
    private const LISTS = [
        'sp_proc_stmts:', 'sp_proc_stmts: sp_proc_stmts sp_proc_stmt ;', 'sp_proc_stmts1: sp_proc_stmt ;', 'sp_proc_stmts1: sp_proc_stmts1 sp_proc_stmt ;',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one statement of a program: a node of `sp_proc_stmt` or `ev_sql_stmt_inner`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): ProgramStatement|Statement
    {
        $form = $this->lowering->form($statement);
        if (!isset(self::ALTERNATIVES[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $inner = $this->lowering->form($form->node(0));
        $flow = new FlowRule($this->lowering);
        $blocks = new BlockRule($this->lowering);

        return match ($inner->signature) {
            'sp_proc_stmt_statement: statement', 'sp_proc_stmt_statement: simple_statement' => $this->lowering->statement($inner->node(0)),
            'sp_proc_stmt_unlabeled: sp_unlabeled_control' => $flow->loop($inner->node(0), null, null),
            default => match ($inner->node->name) {
                'sp_proc_stmt_return', 'sp_proc_stmt_leave', 'sp_proc_stmt_iterate' => $flow->jump($inner),
                'sp_proc_stmt_if' => $flow->conditional($inner),
                'case_stmt_specification' => $flow->choice($inner),
                'sp_labeled_control' => $flow->labeled($inner),
                'sp_labeled_block', 'sp_unlabeled_block' => $blocks->block($inner),
                'sp_proc_stmt_open', 'sp_proc_stmt_fetch', 'sp_proc_stmt_close' => $blocks->cursor($inner),
                default => throw ImplementationGap::production($inner),
            },
        };
    }

    /**
     * Lowers a statement list: a node of `sp_proc_stmts` or `sp_proc_stmts1`.
     *
     * @return list<ProgramStatement|Statement>
     * @throws ImplementationGap When a production has no rule
     */
    public function statements(Node $list): array
    {
        $statements = [];
        foreach ((new Sequence($this->lowering))->items($list, self::LISTS) as $item) {
            $statements[] = $this->statement($item);
        }

        return $statements;
    }

    /**
     * Lowers the statement of an event: a node of `ev_sql_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function event(Node $statement): ProgramStatement|Statement
    {
        $form = $this->lowering->form($statement);

        return $form->signature === 'ev_sql_stmt: ev_sql_stmt_inner' ? $this->statement($form->node(0)) : throw ImplementationGap::production($form);
    }

    /**
     * Lowers a routine body: a node of `sp_proc_stmt` or `stored_routine_body`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function body(Node $body): ExternalBody|ProgramStatement|Statement
    {
        if ($body->name !== 'stored_routine_body') {
            return $this->statement($body);
        }
        $form = $this->lowering->form($body);
        if ($form->signature === 'stored_routine_body: sp_proc_stmt') {
            return $this->statement($form->node(0));
        }
        if ($form->signature !== 'stored_routine_body: AS routine_string') {
            throw ImplementationGap::production($form);
        }
        $string = $this->lowering->form($form->node(1));

        return new ExternalBody(match ($string->signature) {
            'routine_string: TEXT_STRING_literal' => $this->lowering->literals->text($string->node(0)),
            'routine_string: DOLLAR_QUOTED_STRING_SYM' => $this->dollarQuoted($string->token(0)),
            default => throw ImplementationGap::production($string),
        });
    }

    /**
     * Lowers a dollar-quoted string token into its tag and its text.
     */
    public function dollarQuoted(Token $token): DollarQuotedText
    {
        $length = (int) strpos($token->text, '$', 1) + 1;

        return $this->lowering->leaves->record(new DollarQuotedText(substr($token->text, $length, strlen($token->text) - 2 * $length), substr($token->text, 1, $length - 2)));
    }
}
