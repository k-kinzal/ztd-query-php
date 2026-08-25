<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Joins SQL tokens into a string with correct spacing.
 *
 * Shared across all SQL generators. Handles common spacing rules
 * (parentheses, dots, brackets, commas) while supporting dialect-specific
 * no-space pairs.
 */
final class TokenJoiner
{
    /**
     * @param list<string> $tokens SQL tokens to join
     * @param list<list<string>> $noSpacePairs Additional [prev, token] pairs that need no space.
     *                                          Use '*' as wildcard for either position.
     * @param (callable(): string)|null $separator Generates trivia for boundaries that require separation.
     * @param (callable(): string)|null $optionalTrivia Generates optional trivia at the outer and compact boundaries.
     */
    public static function join(
        array $tokens,
        array $noSpacePairs = [],
        ?callable $separator = null,
        ?callable $optionalTrivia = null,
    ): string {
        if ($tokens === []) {
            return '';
        }

        $out = '';
        $prev = null;
        $count = count($tokens);

        if ($optionalTrivia !== null) {
            $out .= $optionalTrivia();
        }

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($prev === null) {
                $out .= $token;
                $prev = $token;
                continue;
            }

            $needsSpace = true;

            if ($token === '(' && self::isIdentifier($prev)) {
                $needsSpace = false;
            } elseif ($token === ')' || $prev === '(' || $token === ',' || $token === ';') {
                $needsSpace = false;
            } elseif ($prev === '.' || $token === '.') {
                $needsSpace = false;
            } elseif ($prev === '[' || $token === ']') {
                $needsSpace = false;
            } elseif (self::matchesNoSpacePair($noSpacePairs, $prev, $token)) {
                $needsSpace = false;
            }

            if ($needsSpace) {
                $out .= $separator !== null ? $separator() : ' ';
            } elseif ($optionalTrivia !== null) {
                $out .= $optionalTrivia();
            }

            $out .= $token;
            $prev = $token;
        }

        if ($optionalTrivia !== null) {
            $out .= $optionalTrivia();
        }

        return $out;
    }

    /**
     * @param list<list<string>> $noSpacePairs
     */
    private static function matchesNoSpacePair(array $noSpacePairs, ?string $prev, string $token): bool
    {
        foreach ($noSpacePairs as $pair) {
            if (($pair[0] === '*' || $pair[0] === $prev)
                && ($pair[1] === '*' || $pair[1] === $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a token looks like an SQL identifier (word or quoted).
     */
    public static function isIdentifier(string $token): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1
            || self::isQuotedIdentifier($token);
    }

    /**
     * Reports whether a token is an identifier written inside quotes.
     *
     * A quoted identifier carries its own boundaries, so it never needs a space
     * between itself and what comes next.
     *
     * @param string $token Token to judge
     *
     * @return bool True when the token opens and closes with the same quote
     */
    public static function isQuotedIdentifier(string $token): bool
    {
        $len = strlen($token);
        if ($len < 2) {
            return false;
        }
        $first = $token[0];

        return ($first === '"' || $first === '`') && $token[$len - 1] === $first;
    }
}
