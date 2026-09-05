<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use RuntimeException;

/**
 * Tokenizes Bison grammar text with buffered lookahead.
 *
 * Scanning and buffering are delegated to separate collaborators while this
 * facade retains the original lexer API and named constructor argument.
 */
final class BisonLexer
{
    private readonly BisonTokenStream $tokens;

    /**
     * @param string $input Bison grammar text
     */
    public function __construct(string $input)
    {
        $this->tokens = BisonTokenStream::over($input);
    }

    /**
     * Consumes the next token, including any buffered lookahead.
     */
    public function next(): BisonToken
    {
        return $this->tokens->next();
    }

    /**
     * Reads the next token without consuming it.
     */
    public function peek(): BisonToken
    {
        return $this->tokens->peek();
    }

    /**
     * Reads the nth token ahead without consuming any tokens.
     *
     * @throws RuntimeException When the lookahead distance is less than one
     */
    public function peekN(int $n): BisonToken
    {
        if ($n < 1) {
            throw new RuntimeException('peekN($n) requires $n >= 1');
        }

        return $this->tokens->peekN($n);
    }

    /**
     * Consumes the next token and returns its text.
     */
    public function nextString(): string
    {
        return $this->tokens->nextString();
    }

    /**
     * Consumes the next token and returns its integer value.
     */
    public function nextInt(): int
    {
        return $this->tokens->nextInt();
    }

    /**
     * Discards lookahead and consumes the remaining source as raw text.
     */
    public function consumeRemaining(): string
    {
        return $this->tokens->consumeRemaining();
    }
}
