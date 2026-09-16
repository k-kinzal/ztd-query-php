<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

use SqlParser\Compiler\GrammarSourceException;

/**
 * Walks the tokens of a Bison grammar file in order.
 *
 * @visibility root
 */
final class BisonTokens
{
    private int $position = 0;

    /**
     * @param list<BisonToken> $tokens Tokens as the scanner produced them
     */
    public function __construct(private readonly array $tokens)
    {
    }

    /**
     * Answers a token ahead of the current position without consuming it.
     *
     * @param int $ahead Distance from the current position
     *
     * @return BisonToken|null The token, or null past the end
     */
    public function peek(int $ahead = 0): ?BisonToken
    {
        return $this->tokens[$this->position + $ahead] ?? null;
    }

    /**
     * Consumes and answers the current token.
     *
     * @return BisonToken|null The token, or null past the end
     */
    public function next(): ?BisonToken
    {
        return $this->tokens[$this->position++] ?? null;
    }

    /**
     * Reports whether every token has been consumed.
     *
     * @return bool True past the end
     */
    public function atEnd(): bool
    {
        return !isset($this->tokens[$this->position]);
    }

    /**
     * Consumes the current token, which must be of a given kind.
     *
     * @param BisonTokenKind $kind Kind the token must have
     * @param string $expected What to say was expected when it is not
     *
     * @return BisonToken The consumed token
     *
     * @throws GrammarSourceException When the token is missing or of another kind
     */
    public function take(BisonTokenKind $kind, string $expected): BisonToken
    {
        $token = $this->next();
        if ($token === null) {
            $last = $this->tokens[count($this->tokens) - 1] ?? null;

            throw GrammarSourceException::unexpected($expected, 'the end of the grammar', $last->line ?? 1);
        }
        if ($token->kind !== $kind) {
            throw GrammarSourceException::unexpected($expected, "'{$token->text}'", $token->line);
        }

        return $token;
    }
}
