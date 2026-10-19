<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

/**
 * Preserves prohibited nested declaration headers before later body operands.
 *
 * The caller has identified a stored instruction. CREATE routines and triggers
 * stop at their header; an event definition with a body stops before that body.
 * Observed on MySQL 8.0, 8.4 and 9.1 without consulting implementation code.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDefinitions
{
    /**
     * Refuses legacy LOAD immediately after its DATA or XML format in a stored body.
     * Later file syntax and operand names have not been interpreted at this point.
     */
    public function load(Node $node, bool $legacy): ?RejectedInput
    {
        if (!$legacy || $node->name !== 'data_or_xml') {
            return null;
        }
        $format = $node->tokens()[0];
        return new RejectedInput(new ProgramProblem(ProgramRule::BadStatement, 'LOAD ' . strtoupper($format->text)), $format->end(), true);
    }

    /**
     * Reports the ALTER VIEW branch before its required VIEW token, including incomplete input.
     * The completed view_suid production distinguishes this branch from other ALTER forms.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens Transient tokens of the current input
     */
    public function alter(Node $node, \SqlParser\Lexer\Token $lookahead, array $tokens): ?RejectedInput
    {
        if ($node->name !== 'view_suid') {
            return null;
        }
        $command = null;
        foreach ($tokens as $token) {
            if ($token->offset >= $lookahead->offset) {
                break;
            }
            if (in_array($token->name, ['ALTER', 'CREATE'], true)) {
                $command = $token->name;
            }
        }
        return $command === 'ALTER' ? new RejectedInput(new ProgramProblem(ProgramRule::BadStatement, 'ALTER VIEW'), $lookahead->offset, true) : null;
    }

    /**
     * Refuses a completed HANDLER name followed by its operation keyword in a stored body.
     *
     * READ, OPEN and CLOSE stop before later operands, including incomplete reads.
     * A DECLARE ... HANDLER condition handler has a different grammar role.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens Transient tokens of the current input
     * @param bool $legacy Whether the operation keyword is the stopping boundary (MySQL 5.6/5.7)
     */
    public function handler(Node $node, \SqlParser\Lexer\Token $lookahead, array $tokens, bool $legacy = true): ?RejectedInput
    {
        if (!$legacy) {
            return in_array($node->name, ['handler', 'handler_stmt'], true) ? $this->rejected($node) : null;
        }
        $allowed = $lookahead->name === 'OPEN_SYM' ? ['table_ident'] : (in_array($lookahead->name, ['READ_SYM', 'CLOSE_SYM'], true) ? ['table_ident_nodb', 'ident'] : []);
        if (!in_array($node->name, $allowed, true)) {
            return null;
        }
        $begin = (new SourceRanges())->span($node)[0] ?? 0;
        $previous = null;
        foreach ($tokens as $token) {
            if ($token->offset >= $begin) {
                break;
            }
            $previous = $token;
        }
        return $previous?->name === 'HANDLER_SYM' ? new RejectedInput(new ProgramProblem(ProgramRule::BadStatement, 'HANDLER'), $lookahead->end(), true) : null;
    }

    /**
     * Answers an immediate nested declaration restriction at its input boundary.
     */
    public function rejected(Node $node, bool $legacy = false): ?RejectedInput
    {
        $load = $this->load($node, $legacy);
        if ($load !== null) {
            return $load;
        }
        if (in_array($node->name, ['handler', 'handler_stmt'], true)) {
            $operation = $node->children[2];
            \SqlSemantics\Diagnostic\Check::invariant($operation instanceof \SqlParser\Lexer\Token, 'A HANDLER statement has an operation keyword.');
            return new RejectedInput(new ProgramProblem(ProgramRule::BadStatement, 'HANDLER'), $operation->end(), true);
        }
        $kind = match ($node->name) {
            'sp_tail' => 'PROCEDURE',
            'sf_tail' => 'FUNCTION',
            'trigger_tail' => 'TRIGGER',
            default => null,
        };
        if ($kind !== null) {
            return new RejectedInput(new ProgramProblem(ProgramRule::RecursiveCreate, $kind), $node->tokens()[0]->end(), true);
        }
        if (!in_array($node->name, ['event_tail', 'alter_event_stmt', 'alter'], true)) {
            return null;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && ($child->name === 'ev_sql_stmt' || ($child->name === 'opt_ev_sql_stmt' && $child->children !== []))) {
                return new RejectedInput(new ProgramProblem(ProgramRule::EventRecursion), (new SourceRanges())->span($child)[0] ?? 0, true);
            }
        }
        return null;
    }
}
