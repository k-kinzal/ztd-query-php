<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;

/**
 * Tokens read one at a time, with one token of lookahead.
 *
 * @visibility root
 */
final class TokenStream
{
    private int $position = 0;

    /**
     * @param non-empty-list<Token> $tokens The tokens, ending with an end-of-file token
     */
    public function __construct(private readonly array $tokens)
    {
    }

    /**
     * Looks at the next token without taking it.
     *
     * @return Token The next token, or the end-of-file token
     */
    public function peek(): Token
    {
        return $this->tokens[$this->position] ?? $this->tokens[count($this->tokens) - 1];
    }

    /**
     * Takes the next token.
     *
     * @return Token The token taken; the end-of-file token is taken again and again
     */
    public function next(): Token
    {
        $token = $this->peek();
        if ($this->position < count($this->tokens) - 1) {
            $this->position++;
        }

        return $token;
    }

    /**
     * Reports whether the file has ended.
     *
     * @return bool True at the end-of-file token
     */
    public function eof(): bool
    {
        return $this->peek()->is(TokenKind::End);
    }
}
