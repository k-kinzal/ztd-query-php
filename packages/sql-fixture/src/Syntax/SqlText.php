<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Writes a part of a syntax tree back as the SQL it was parsed from.
 *
 * The tree keeps every byte of the statement: each token carries the
 * whitespace and comments written before it. A fragment is therefore written
 * back from its own first token, so what was written inside it is kept and
 * the trivia in front of it is left to whatever precedes it.
 *
 * @visibility root
 */
final class SqlText
{
    /**
     * Answers the SQL a node was written as.
     */
    public function ofNode(Node $node): string
    {
        return $this->ofTokens($node->tokens());
    }

    /**
     * Answers the SQL a run of tokens was written as.
     *
     * @param list<Token> $tokens The tokens in text order
     */
    public function ofTokens(array $tokens): string
    {
        $first = array_shift($tokens);
        if ($first === null) {
            return '';
        }

        $text = $first->text;
        foreach ($tokens as $token) {
            $text .= $token->toString();
        }

        return $text;
    }
}
