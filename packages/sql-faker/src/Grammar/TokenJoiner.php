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
     */
    public static function join(array $tokens, array $noSpacePairs = []): string
    {
        $out = '';
        $prev = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($out === '') {
                $out = $token;
                $prev = $token;
                continue;
            }

            $needsSpace = true;

            if ($token === '(' && $prev !== null && self::isIdentifier($prev)) {
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
                $out .= ' ';
            }

            $out .= $token;
            $prev = $token;
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
