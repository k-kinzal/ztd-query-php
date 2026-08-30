<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * How one dialect closes a run of text it opened with a quote.
 *
 * Strings and identifiers are both written between delimiters, and a dialect
 * decides which pairs spell each, whether a run may instead be closed by a
 * tag of its own, and whether a backslash escapes inside a string.
 */
final class SqlQuoteProfile
{
    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $stringQuotePairs;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $identifierQuotePairs;

    /** @var list<non-empty-string> */
    private readonly array $backslashEscapedStringPrefixes;

    /**
     * @param array<string, string> $stringQuotePairs Opening quote => the one that closes the string
     * @param array<string, string> $identifierQuotePairs Opening quote => the one that closes the identifier
     * @param string|null $dollarQuoteDelimiterPattern Pattern a dollar-quoted delimiter is written as, or null where the dialect has none
     * @param list<string> $backslashEscapedStringPrefixes Prefixes that make the string they introduce use backslash escapes
     * @param bool $backslashEscapedStrings Whether every string uses backslash escapes
     * @param LexicalPattern $patterns Reads a regular expression against a position
     * @param LexicalDelimiters $delimiters Refuses lexical data a scanner could not use
     *
     * @throws InvalidDefinitionException When a delimiter is empty or a pattern is unreadable
     */
    public function __construct(
        array $stringQuotePairs,
        array $identifierQuotePairs,
        private readonly ?string $dollarQuoteDelimiterPattern,
        array $backslashEscapedStringPrefixes,
        private readonly bool $backslashEscapedStrings,
        private readonly LexicalPattern $patterns = new LexicalPattern(),
        LexicalDelimiters $delimiters = new LexicalDelimiters(),
    ) {
        $this->stringQuotePairs = $delimiters->pairs($stringQuotePairs, 'String quote');
        $this->identifierQuotePairs = $delimiters->pairs($identifierQuotePairs, 'Identifier quote');
        $this->backslashEscapedStringPrefixes = $delimiters->nonEmpty($backslashEscapedStringPrefixes);
        $this->patterns->assertValid($this->dollarQuoteDelimiterPattern);
    }

    /**
     * Answers the quote that closes a string this one opened.
     *
     * @param string $opening Quote that opened it
     *
     * @return string|null The closing quote, or null when nothing opens a string with that
     */
    public function stringQuoteClosing(string $opening): ?string
    {
        return $this->stringQuotePairs[$opening] ?? null;
    }

    /**
     * Answers the quote that closes an identifier this one opened.
     *
     * @param string $opening Quote that opened it
     *
     * @return string|null The closing quote, or null when nothing opens an identifier with that
     */
    public function identifierQuoteClosing(string $opening): ?string
    {
        return $this->identifierQuotePairs[$opening] ?? null;
    }

    /**
     * Answers the name a quoted identifier stands for.
     *
     * A closing quote doubled inside the name is one such character rather than
     * the end of it, which is how every dialect here writes a quote in a name.
     *
     * @param string $identifier Identifier as it was written
     *
     * @return string The name, or the identifier unchanged when it was not quoted
     */
    public function unquoteIdentifier(string $identifier): string
    {
        foreach ($this->identifierQuotePairs as $opening => $closing) {
            if (!str_starts_with($identifier, $opening) || !str_ends_with($identifier, $closing)) {
                continue;
            }
            $body = substr($identifier, strlen($opening), -strlen($closing));

            return str_replace($closing . $closing, $closing, $body);
        }

        return $identifier;
    }

    /**
     * Answers the name a quoted identifier stands for, and nothing for anything else.
     *
     * This differs from unquoteIdentifier() in what it says about an identifier
     * that was never quoted: here that is not an identifier this can speak for,
     * rather than one that stands for itself.
     *
     * @param string $identifier Identifier as it was written
     *
     * @return string|null The name, or null when it was not a complete quoted identifier
     */
    public function quotedIdentifierValue(string $identifier): ?string
    {
        foreach ($this->identifierQuotePairs as $opening => $closing) {
            if (!str_starts_with($identifier, $opening)) {
                continue;
            }
            if (strlen($identifier) <= strlen($opening) + strlen($closing)
                || !str_ends_with($identifier, $closing)
            ) {
                return null;
            }
            $body = substr($identifier, strlen($opening), -strlen($closing));

            return str_replace($closing . $closing, $closing, $body);
        }

        return null;
    }

    /**
     * Answers the dollar-quoted delimiter starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The delimiter, or null when none starts there
     */
    public function dollarQuoteDelimiterAt(string $sql, int $offset): ?string
    {
        return $this->patterns->matchAt($this->dollarQuoteDelimiterPattern, $sql, $offset);
    }

    /**
     * Reports whether the string opening here treats a backslash as an escape.
     *
     * Some dialects say so for every string; others only for a string introduced
     * by a particular prefix, and only where that prefix is a prefix rather than
     * the tail of an identifier, which is why what spells an identifier is asked.
     *
     * @param string $sql Statement being scanned
     * @param int $quoteOffset Position of the quote that opens the string
     * @param SqlSymbolProfile $symbols What the dialect spells an identifier with
     *
     * @return bool True when a backslash escapes inside it
     */
    public function stringUsesBackslashEscapes(string $sql, int $quoteOffset, SqlSymbolProfile $symbols): bool
    {
        if ($this->backslashEscapedStrings) {
            return true;
        }
        foreach ($this->backslashEscapedStringPrefixes as $prefix) {
            $prefixLength = strlen($prefix);
            if ($quoteOffset < $prefixLength) {
                continue;
            }
            $prefixOffset = $quoteOffset - $prefixLength;
            if (substr_compare($sql, $prefix, $prefixOffset, $prefixLength) !== 0) {
                continue;
            }
            $preceding = $prefixOffset === 0 ? '' : $sql[$prefixOffset - 1];
            if ($preceding === '' || !$symbols->isIdentifierPart($preceding)) {
                return true;
            }
        }

        return false;
    }
}
