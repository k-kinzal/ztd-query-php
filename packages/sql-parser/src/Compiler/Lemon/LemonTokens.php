<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;

/**
 * Walks the tokens of a Lemon grammar file in order.
 *
 * @visibility root
 */
final class LemonTokens
{
    private int $position = 0;

    /**
     * @param list<LemonToken> $tokens Tokens as the scanner produced them
     */
    public function __construct(private readonly array $tokens)
    {
    }

    /**
     * Answers a token ahead of the current position without consuming it.
     *
     * @param int $ahead Distance from the current position
     *
     * @return LemonToken|null The token, or null past the end
     */
    public function peek(int $ahead = 0): ?LemonToken
    {
        return $this->tokens[$this->position + $ahead] ?? null;
    }

    /**
     * Consumes and answers the current token.
     *
     * @return LemonToken|null The token, or null past the end
     */
    public function next(): ?LemonToken
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
     * @param LemonTokenKind $kind Kind the token must have
     * @param string $expected What to say was expected when it is not
     *
     * @return LemonToken The consumed token
     *
     * @throws GrammarSourceException When the token is missing or of another kind
     */
    public function take(LemonTokenKind $kind, string $expected): LemonToken
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

    /**
     * Consumes identifiers up to and including the dot that ends a directive.
     *
     * @return list<string> The identifiers in order
     *
     * @throws GrammarSourceException When the list never ends
     */
    public function namesUntilDot(): array
    {
        $names = [];
        while (($token = $this->next()) !== null) {
            if ($token->is(LemonTokenKind::Dot)) {
                return $names;
            }
            if ($token->is(LemonTokenKind::Identifier)) {
                $names[] = $token->text;
            }
        }

        throw GrammarSourceException::unterminated('symbol list', 1);
    }
}
