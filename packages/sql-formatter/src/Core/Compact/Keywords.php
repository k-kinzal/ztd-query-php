<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Normalizes terminals according to supplied identifier and keyword declarations.
 *
 * @visibility SqlFormatter
 */
final class Keywords
{
    /**
     * @param list<string> $identifiers Rules that own identifiers
     * @param list<string> $verbatim Terminal kinds whose spelling must be preserved
     * @param array<string, string> $aliases Canonical spellings by terminal kind
     * @param list<string> $fallbackLiterals Literal kinds excluded from identifier fallback
     */
    public function __construct(
        private readonly array $identifiers,
        private readonly array $verbatim,
        private readonly array $aliases,
        private readonly ?string $fallbackRule = null,
        private readonly array $fallbackLiterals = [],
        private readonly ?string $excludedParent = null,
    ) {
    }

    /**
     * Determines whether a rule owns an identifier in this context.
     */
    public function identifier(string $rule, ?Node $parent = null): bool
    {
        return in_array($rule, $this->identifiers, true) && ($this->excludedParent === null || $parent?->name !== $this->excludedParent);
    }

    /**
     * Determines whether a terminal was accepted in an identifier fallback position.
     */
    public function fallback(Token $token, ?Node $parent): bool
    {
        return $parent !== null && $parent->name === $this->fallbackRule && count($parent->children) === 1
            && !in_array($token->name, $this->fallbackLiterals, true);
    }

    /**
     * Canonicalizes known aliases while preserving names, literals, and directives.
     */
    public function text(Token $token, bool $identifier): string
    {
        if ($identifier || in_array($token->name, $this->verbatim, true)) {
            return $token->text;
        }
        if (isset($this->aliases[$token->name]) && preg_match('~/\*[+!]|/\*M!~', $token->text) !== 1) {
            return $this->aliases[$token->name];
        }
        return preg_match('/^[a-zA-Z_]+$/D', $token->text) === 1 ? strtoupper($token->text) : $token->text;
    }
}
