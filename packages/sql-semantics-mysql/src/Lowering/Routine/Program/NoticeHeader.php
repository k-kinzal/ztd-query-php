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
            if ($this->kind !== null || $this->name === null) {
                return null;
            }
            if ($node->name === 'sp_c_chistics') {
                $function = array_filter($tokens, fn (Token $token): bool => $token->offset < $this->nameEnd && strtoupper($token->text) === 'FUNCTION') !== [];
                $this->kind = $function ? ProgramKind::Function : ProgramKind::Procedure;
            } elseif ($node->name === 'trigger_follows_precedes_clause' || $node->name === 'trg_event') {
                $this->kind = ProgramKind::Trigger;
            } elseif ($node->name === 'opt_ev_comment' && $lookahead->name === 'DO_SYM') {
                $this->kind = ProgramKind::Event;
            }
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return $failure;
        }

        return null;
    }

    /**
     * Attaches the typed header to an empty event instruction without publishing an invalid body.
     */
    public function scoped(ParseFailure $notice): ParseFailure
    {
        if (!$notice->problem instanceof EmptyEventAlteration || $this->name === null || $this->kind === null) {
            return $notice;
        }

        return new ParseFailure(new RefusedProgramBody($this->name, $this->kind, $notice->problem, $this->definer, nameEnd: $this->nameEnd), $notice->aborts, $notice->phase);
    }
}
