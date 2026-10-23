<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery;

/**
 * Consumes a PURGE operand before publishing its query-free semantic value.
 *
 * Modern releases read the complete expression before refusing its first
 * subquery. Grammar warnings and operand errors still precede this refusal.
 * The returned diagnostic carries a position only.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxLogMoment
{
    /**
     * Recognizes MySQL 5.6's refusal at the parser's subselect-start reduction.
     * The accepted prefix identifies the current PURGE operand even when its
     * expression never completes; a statement separator ends that context.
     */
    public function starting(Node $node, \SqlParser\Lexer\Token $lookahead, \SqlParser\Parser\ReductionParser $parser, string $statement, GrammarRelease $release): ?RejectedInput
    {
        if ($release !== GrammarRelease::MySql5651 || $node->name !== 'subselect_start') {
            return null;
        }
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $statement);
        $purge = false;
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $lookahead->offset) {
                if (!$purge) {
                    return null;
                }
                $open = $tokens[$index - 1] ?? null;
                $before = $tokens[$index - 2] ?? null;
                $offset = $open?->name === '(' && in_array($before?->name, ['EXISTS', 'ALL', 'ANY_SYM', 'SOME'], true) ? $open->offset : $lookahead->offset;

                return new RejectedInput(new RefusedLogSubquery($offset), $offset + 1);
            }
            if ($token->name === ';') {
                $purge = false;
            } elseif ($token->name === 'PURGE') {
                $purge = in_array($tokens[$index + 1]->name ?? '', ['MASTER_SYM', 'BINARY'], true)
                    && ($tokens[$index + 2]->name ?? '') === 'LOGS_SYM' && ($tokens[$index + 3]->name ?? '') === 'BEFORE_SYM';
            }
        }

        return null;
    }

    /**
     * Answers the first forbidden query of a completed PURGE operand.
     */
    public function rejected(Node $node, GrammarRelease $release): ?RejectedInput
    {
        if ($node->name !== 'purge_option') {
            return null;
        }
        $ranges = new SourceRanges();
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if ($value->name === 'subquery') {
                return new RejectedInput(new RefusedLogSubquery($ranges->span($value)[0] ?? 0), $ranges->span($node)[1] ?? 0, false);
            }
            if ($value->name === 'subselect') {
                return $this->legacy($node, $value, $release);
            }
            foreach (array_reverse($value->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * Preserves the legacy refusal position and its earlier temporal-check boundary.
     */
    public function legacy(Node $operand, Node $query, GrammarRelease $release): RejectedInput
    {
        $first = $query->tokens()[0];
        $tokens = array_values(array_filter($operand->tokens(), static fn (\SqlParser\Lexer\Token $token): bool => $token->offset < $first->offset));
        $open = $tokens[count($tokens) - 1];
        $previous = $tokens[count($tokens) - 2] ?? null;
        $early = $release === GrammarRelease::MySql5651;
        $offset = $early && in_array($previous?->name, ['EXISTS', 'ALL', 'ANY_SYM', 'SOME'], true) ? $open->offset : ($early ? $first->offset : $open->end());

        return new RejectedInput(new RefusedLogSubquery($offset), $early ? $offset + 1 : $first->offset, $early);
    }
}
