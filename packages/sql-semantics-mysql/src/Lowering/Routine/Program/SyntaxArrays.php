<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

/**
 * Interprets the program-only ARRAY restriction at the closing cast parenthesis.
 *
 * Grammar reductions distinguish a stored body from an event schedule. This
 * transient input state never escapes into semantic values. MySQL 8.0 and 8.1
 * continue through the current instruction; newer releases stop immediately.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxArrays
{
    private bool $body = false;

    /**
     * Observes declaration headers and returns a completed body's array refusal.
     */
    public function completed(Node $node, Token $lookahead, Lowering $lowering, ?int $failedAt = null): ?ParseFailure
    {
        $this->body = $this->body || in_array($node->name, ['sp_c_chistics', 'trigger_follows_precedes_clause'], true)
            || ($node->name === 'opt_ev_comment' && $lookahead->name === 'DO_SYM');
        $problem = $this->body ? $this->rejected($node, $lowering) : null;
        if ($failedAt !== null && ((new SourceRanges())->span($node)[0] ?? PHP_INT_MAX) < $failedAt) {
            return null;
        }

        return $problem === null ? null : new ParseFailure($problem->problem, $problem->aborts);
    }

    /**
     * Answers a cast refusal within an already identified program instruction.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'simple_expr') {
            return null;
        }
        $form = $lowering->form($node);
        if ($form->signature !== 'simple_expr: CAST_SYM ( expr AS cast_type opt_array_cast )'
            || !(new \SqlSemantics\Platform\MySql\Lowering\Expression\ConstructRule($lowering))->flag($form->node(5))) {
            return null;
        }
        return new RejectedInput(new ProgramProblem(ProgramRule::ArrayCast), (new SourceRanges())->span($node)[1] ?? 0, !in_array($lowering->profile->grammar, [GrammarRelease::MySql8044, GrammarRelease::MySql810], true));
    }
}
