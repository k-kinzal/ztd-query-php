<?php

declare(strict_types=1);

namespace BisonParser\Syntax;

use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;

/**
 * Walks the tokens of a grammar file in order.
 *
 * @visibility root
 */
final class TokenStream
{
    private int $position = 0;

    /**
     * @param list<Token> $tokens Tokens as the scanner produced them, ending with the end-of-file token
     */
    public function __construct(private readonly array $tokens)
    {
    }

    /**
     * Answers a token ahead of the current position without consuming it.
     *
     * @param int $ahead Distance from the current position
     *
     * @return Token The token, the end-of-file token past the end
     */
    public function peek(int $ahead = 0): Token
    {
        return $this->tokens[$this->position + $ahead] ?? $this->tokens[count($this->tokens) - 1];
    }

    /**
     * Consumes and answers the current token.
     *
     * @return Token The token, the end-of-file token past the end
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
     * Reports whether the current token is of a given kind.
     *
     * @param TokenKind $kind Kind to look for
     *
     * @return bool True when the current token is of that kind
     */
    public function is(TokenKind $kind): bool
    {
        return $this->peek()->kind === $kind;
    }

    /**
     * Reports whether the current token is a given directive.
     *
     * @param string $name Canonical directive name without the percent sign
     *
     * @return bool True for that directive
     */
    public function isDirective(string $name): bool
    {
        return $this->peek()->isDirective($name);
    }

    /**
     * Consumes the current token when it is of a given kind.
     *
     * @param TokenKind $kind Kind to look for
     *
     * @return Token|null The consumed token, or null when the current token is of another kind
     */
    public function accept(TokenKind $kind): ?Token
    {
        return $this->is($kind) ? $this->next() : null;
    }

    /**
     * Consumes the current token, which must be of a given kind.
     *
     * @param TokenKind $kind Kind the token must have
     * @param string $expected What to say was expected when it is not
     *
     * @return Token The consumed token
     *
     * @throws SyntaxException When the current token is of another kind
     */
    public function expect(TokenKind $kind, string $expected): Token
    {
        $token = $this->peek();
        if ($token->kind !== $kind) {
            throw SyntaxException::unexpected($expected, $token->describe(), $token->location);
        }

        return $this->next();
    }
}
