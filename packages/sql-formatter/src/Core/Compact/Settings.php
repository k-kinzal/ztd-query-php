<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

use Closure;
use SqlParser\Lexer\Token;

/**
 * Grammar-specific canonicalization inputs, independent of parser selection.
 *
 * @visibility SqlFormatter
 */
final class Settings
{
    /**
     * @param array<string, string|null> $aliases Alias rule to required parent, or any parent
     * @param Closure(string, int, int, int, bool): bool $nesting Whether an inner block comment nests
     * @param Closure(Token, Token, Token|null): (string|null) $separator Required lexical separator, or the lexer decision
     */
    public function __construct(
        public readonly Keywords $keywords,
        public readonly Rules $rules,
        public readonly Grouping $grouping,
        public readonly array $aliases,
        public readonly Closure $nesting,
        public readonly Closure $separator,
    ) {
    }
}
