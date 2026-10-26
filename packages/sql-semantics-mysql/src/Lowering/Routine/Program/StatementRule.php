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
use SqlSemantics\Platform\MySql\Statement\Statement;

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
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a completed operand is outside the selected SQL grammar
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

        $meaning = match ($inner->signature) {
            'sp_proc_stmt_statement: statement', 'sp_proc_stmt_statement: simple_statement' => $this->sql($inner->node(0)),
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
        return $this->lowering->origins->record($meaning, $statement);
    }

    /**
     * Lowers an SQL program member, refusing client-only connection explanations.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the statement has no stored-program form
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a completed operand is outside the selected SQL grammar
     */
    public function sql(Node $node): ProgramStatement|Statement
    {
        $process = (new \SqlSemantics\Platform\MySql\Lowering\Server\ProcessOperand($this->lowering))->program($node);
        if ($process !== null) {
            return $process;
        }
        $selection = (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections($this->lowering))->read($node);
        if ($selection !== null) {
            return $selection;
        }
        $assignments = (new \SqlSemantics\Platform\MySql\Lowering\Utility\Set\StoredAssignments($this->lowering))->read($node);
        if ($assignments !== null) {
            return $assignments;
        }
        $evaluation = (new \SqlSemantics\Platform\MySql\Lowering\Dml\EvaluationItems($this->lowering))->program($node);
        if ($evaluation !== null) {
            return $evaluation;
        }
        $grant = (new PrivilegeGrant())->read($node, $this->lowering);
        if ($grant !== null) {
            return $grant;
        }
        try {
            $statement = $this->lowering->statement($node);
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return $this->rejected($failure);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('non-standalone EXPLAIN FOR CONNECTION'), (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0);
        }
        return $statement;
    }

    /**
     * Retains deferred SQL members and maps refusals that have no program form.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the refusal cannot be deferred
     */
    public function rejected(\SqlSemantics\Construction\RejectedInput $failure): ProgramStatement|Statement
    {
        $administrative = $this->administrativeRefusal($failure->problem);
        if ($administrative !== null) {
            return $administrative;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\Privilege\RefusedRevokeLevel || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole) {
            return $failure->problem->request;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\RefusedUpdate || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery) {
            return $failure->problem->write;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain) {
            return $failure->problem->explanation;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedIndexTemplate || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RefusedAlterTemplate) {
            return $failure->problem->definition;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery) {
            return $failure->problem->query;
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::NestedAlterOrDrop, 'DATABASE'), $failure->end, false);
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::BadStatement, 'LOAD ' . $failure->problem->format->value), $failure->end, false);
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('non-standalone EXPLAIN FOR CONNECTION'), $failure->end);
        }
        throw $failure;
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
    /**
     * Retains administrative requests whose conditions belong to invocation.
     */
    public function administrativeRefusal(\SqlSemantics\Statement\Fact\Diagnostic $problem): ?ProgramStatement
    {
        return match (true) {
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart => $problem->request,
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge($problem->moment),
            default => null,
        };
    }

}
