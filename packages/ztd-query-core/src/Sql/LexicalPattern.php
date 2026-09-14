<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * Reads a regular expression against a position in a statement.
 *
 * A scanner asks the same two questions over and over — does this pattern
 * match right here, and does this one character match at all — and both have
 * an answer that has to be exact: an empty match is no match, because a
 * scanner that accepts one never advances.
 */
final class LexicalPattern
{
    /**
     * Answers what a pattern matches at a position, if anything.
     *
     * @param string|null $pattern Pattern to read, or null when the dialect has none
     * @param string $subject Statement being scanned
     * @param int $offset Position to read from
     *
     * @return string|null What it matched, or null when it matched nothing
     */
    public function matchAt(?string $pattern, string $subject, int $offset): ?string
    {
        if ($pattern === null || preg_match($pattern, substr($subject, $offset), $matches) !== 1) {
            return null;
        }

        return $matches[0] === '' ? null : $matches[0];
    }

    /**
     * Reports whether one character matches a pattern.
     *
     * @param string $pattern Pattern to read
     * @param string $character Character to test
     *
     * @return bool True when it matches, and false for no character at all
     */
    public function matchesCharacter(string $pattern, string $character): bool
    {
        return $character !== '' && preg_match($pattern, $character) === 1;
    }

    /**
     * Refuses a pattern that is not one preg can read.
     *
     * preg reports a bad pattern as a warning rather than by raising, so the
     * warning is turned into the refusal here; a pattern kept and used later
     * would fail at every position instead of once, at the point it was given.
     *
     * @param string|null $pattern Pattern to check, or null when the dialect has none
     *
     * @throws InvalidDefinitionException When the pattern is empty or preg will not read it
     */
    public function assertValid(?string $pattern): void
    {
        if ($pattern === null) {
            return;
        }
        set_error_handler(static function (): never {
            throw new InvalidDefinitionException('A lexical pattern must be a valid non-empty regular expression.');
        });
        try {
            $valid = $pattern !== '' && preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
        if (!$valid) {
            throw new InvalidDefinitionException('A lexical pattern must be a valid non-empty regular expression.');
        }
    }
}
