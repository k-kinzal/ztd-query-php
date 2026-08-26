<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * Refuses lexical data a scanner could not use.
 *
 * An empty delimiter matches at every position without consuming anything, so
 * a scanner given one would never move on. Refusing it where the profile is
 * built means the scanner can take every delimiter it is handed as something
 * it can actually advance past.
 */
final class LexicalDelimiters
{
    /**
     * @param LexicalPattern $patterns Reads a regular expression
     */
    public function __construct(private readonly LexicalPattern $patterns = new LexicalPattern())
    {
    }

    /**
     * Answers the delimiters, having refused any that is empty.
     *
     * @param list<string> $values Delimiters as the dialect declared them
     *
     * @return list<non-empty-string> The same delimiters
     *
     * @throws InvalidDefinitionException When one of them is empty
     */
    public function nonEmpty(array $values): array
    {
        foreach ($values as $value) {
            if ($value === '') {
                throw new InvalidDefinitionException('A lexical delimiter must not be empty.');
            }
        }

        return $values;
    }

    /**
     * Answers the opening-to-closing pairs, having refused any empty end.
     *
     * @param array<string, string> $pairs Opening delimiter => the one that closes it
     * @param string $kind What the pairs delimit, for the refusal
     *
     * @return array<non-empty-string, non-empty-string> The same pairs
     *
     * @throws InvalidDefinitionException When either end of a pair is empty
     */
    public function pairs(array $pairs, string $kind): array
    {
        foreach ($pairs as $opening => $closing) {
            if ($opening === '' || $closing === '') {
                throw new InvalidDefinitionException($kind . ' delimiters must not be empty.');
            }
        }

        return $pairs;
    }

    /**
     * Answers the per-prefix lists, having refused any empty prefix or entry.
     *
     * @param array<string, list<string>> $parameters Parameter prefix => delimiters that may follow it
     *
     * @return array<non-empty-string, list<non-empty-string>> The same lists
     *
     * @throws InvalidDefinitionException When a prefix or one of its entries is empty
     */
    public function perPrefixLists(array $parameters): array
    {
        foreach ($parameters as $prefix => $values) {
            if ($prefix === '') {
                throw new InvalidDefinitionException('A parameter prefix must not be empty.');
            }
            $parameters[$prefix] = $this->nonEmpty($values);
        }

        return $parameters;
    }

    /**
     * Answers the per-prefix patterns, having refused any preg cannot read.
     *
     * @param array<string, string> $patterns Parameter prefix => pattern for what may follow its name
     *
     * @return array<non-empty-string, non-empty-string> The same patterns
     *
     * @throws InvalidDefinitionException When a prefix is empty or a pattern is unreadable
     */
    public function perPrefixPatterns(array $patterns): array
    {
        foreach ($patterns as $prefix => $pattern) {
            if ($prefix === '' || $pattern === '') {
                throw new InvalidDefinitionException('Parameter suffix patterns and prefixes must not be empty.');
            }
            $this->patterns->assertValid($pattern);
        }

        return $patterns;
    }

    /**
     * Answers the patterns, having refused any preg cannot read.
     *
     * @param list<string> $patterns Patterns as the dialect declared them
     *
     * @return list<non-empty-string> The same patterns
     *
     * @throws InvalidDefinitionException When one of them is empty or unreadable
     */
    public function validPatterns(array $patterns): array
    {
        $patterns = $this->nonEmpty($patterns);
        foreach ($patterns as $pattern) {
            $this->patterns->assertValid($pattern);
        }

        return $patterns;
    }
}
