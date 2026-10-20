<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Supplies the enclosing declaration to a refused instruction in an unfinished input.
 *
 * The input may stop before a complete routine node exists. Completed header
 * reductions supply typed names; no source text or parser node is retained.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NoticeHeader
{
    private ?QualifiedName $name = null;
    private ?Account $definer = null;
    private ?ProgramKind $kind = null;
    private int $nameEnd = 0;

    /**
     * Reads the first declaration header and recognizes entry into its instruction body.
     *
     * @param list<Token> $tokens The transient input tokens identifying the declaration kind
     * @return \SqlSemantics\Construction\RejectedInput|null The first invalid header operand, when present
     */
    public function observe(Node $node, Token $lookahead, Lowering $lowering, array $tokens): ?\SqlSemantics\Construction\RejectedInput
    {
        try {
            if ($this->name === null && $node->name === 'definer') {
                $this->definer = $lowering->users->definer($node);
            }
            if ($this->name === null && $node->name === 'sp_name') {
                $this->name = $lowering->names->qualified($node);
                $this->nameEnd = (new SourceRanges())->span($node)[1] ?? 0;
            }
            if ($this->name === null) {
                return null;
            }
            if ($this->kind === null && $node->name === 'sp_c_chistics') {
                $function = array_filter($tokens, fn (Token $token): bool => $token->offset < $this->nameEnd && strtoupper($token->text) === 'FUNCTION') !== [];
                $this->kind = $function ? ProgramKind::Function : ProgramKind::Procedure;
            } elseif ($this->kind === null && in_array($node->name, ['trigger_follows_precedes_clause', 'trg_event'], true)) {
                $this->kind = ProgramKind::Trigger;
            } elseif ($this->kind === null && $node->name === 'opt_ev_comment' && $lookahead->name === 'DO_SYM') {
                $this->kind = ProgramKind::Event;
            }
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return $failure;
        }

        $legacy = in_array($lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $failure = $this->kind === null ? null : ((new SyntaxDefinitions())->load($node, $legacy) ?? (new SyntaxDefinitions())->handler($node, $lookahead, $tokens, $legacy) ?? (new SyntaxDefinitions())->alter($node, $lookahead, $tokens));
        if ($failure !== null) {
            \SqlSemantics\Diagnostic\Check::invariant($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem, 'A prohibited stored instruction has a program restriction.');
            return new \SqlSemantics\Construction\RejectedInput(new RefusedProgramBody($this->name, $this->kind, $failure->problem, $this->definer, nameEnd: $this->nameEnd), $failure->end, true);
        }
        return null;
    }

    /**
     * Attaches the typed header to refused instruction operands.
     */
    public function scoped(ParseFailure $notice): ParseFailure
    {
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel && $this->kind !== null) {
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel($notice->problem->privilege, $notice->problem->level, $this->name), $notice->aborts, $notice->phase);
        }
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets && $this->kind !== null) {
            $problem = $notice->problem;
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets($problem->targets, $problem->tables, $problem->with, $problem->where, $this->name), $notice->aborts, $notice->phase);
        }
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget && $this->kind !== null) {
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget($notice->problem->targets, $this->name), $notice->aborts, $notice->phase);
        }
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize && $this->kind !== null) {
            $problem = $notice->problem;
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize($problem->rule, $problem->name, $problem->actual, $problem->maximum, $this->name), $notice->aborts, $notice->phase);
        }
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute && $this->kind !== null) {
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute($notice->problem->column, $notice->problem->attribute, $this->name), $notice->aborts, $notice->phase);
        }
        if ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns && $this->kind !== null) {
            return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns($notice->problem->view, $notice->problem->column, $this->name), $notice->aborts, $notice->phase);
        }
        $problem = $notice->problem;
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem) {
            if (!in_array($problem->rule, [\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::RedefinedLabel, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::EndLabelMismatch, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::LeaveWithoutLabel, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::IterateWithoutLabel], true)) {
                return $notice;
            }
        } elseif (!$problem instanceof EmptyEventAlteration) {
            return $notice;
        }
        if ($this->name === null || $this->kind === null) {
            return $notice;
        }

        return new ParseFailure(new RefusedProgramBody($this->name, $this->kind, $problem, $this->definer, nameEnd: $this->nameEnd), $notice->aborts, $notice->phase);
    }
}
