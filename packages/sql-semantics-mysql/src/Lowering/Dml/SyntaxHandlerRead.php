<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery;

/**
 * Reads HANDLER's query-free operand domain before publishing scalar values.
 *
 * MySQL 5.6 refuses the subselect start, 5.7 the completed nested query,
 * and modern releases its opening parenthesis during contextualization.
 * Positions were observed on native releases without consulting server code.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxHandlerRead
{
    /**
     * Preserves MySQL 5.6's refusal even when the nested query never completes.
     *
     * @param list<Token> $tokens The input prefix, used only during lowering
     */
    public function starting(Node $node, Token $lookahead, array $tokens, GrammarRelease $release): ?RejectedInput
    {
        if ($release !== GrammarRelease::MySql5651 || $node->name !== 'subselect_start') {
            return null;
        }
        $handler = false;
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $lookahead->offset) {
                if (!$handler) {
                    return null;
                }
                $open = $tokens[$index - 1] ?? null;
                $before = $tokens[$index - 2] ?? null;
                $offset = $open?->name === '(' && in_array($before?->name, ['EXISTS', 'ALL', 'ANY_SYM', 'SOME'], true) ? $open->offset : $lookahead->offset;
                return new RejectedInput(new RefusedSubquery($offset), $offset + 1);
            }
            if ($token->name === ';') {
                $handler = false;
            } elseif ($token->name === 'HANDLER_SYM') {
                $handler = ($tokens[$index + 2]->name ?? '') === 'READ_SYM';
            }
        }
        return null;
    }

    /**
     * Refuses the first query in a completed read and retains only its boundary.
     */
    public function rejected(Node $node, GrammarRelease $release): ?RejectedInput
    {
        if (!in_array($node->name, ['handler', 'handler_stmt'], true)) {
            return null;
        }
        $pending = [$node];
        $ranges = new SourceRanges();
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part->name === 'subquery') {
                return new RejectedInput(new RefusedSubquery($ranges->span($part)[0] ?? 0), $ranges->span($node)[1] ?? 0, false);
            }
            if ($part->name === 'subselect') {
                $first = $part->tokens()[0];
                $tokens = array_values(array_filter($node->tokens(), static fn (Token $token): bool => $token->offset < $first->offset));
                $open = $tokens[count($tokens) - 1];
                $before = $tokens[count($tokens) - 2] ?? null;
                $early = $release === GrammarRelease::MySql5651;
                $offset = $early && in_array($before?->name, ['EXISTS', 'ALL', 'ANY_SYM', 'SOME'], true) ? $open->offset : ($early ? $first->offset : $open->end());
                return new RejectedInput(new RefusedSubquery($offset), $early ? $offset + 1 : $first->offset, $early);
            }
            foreach (array_reverse($part->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }
        return null;
    }
}
