<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * Everything the neutral scanner needs to know about one dialect's spelling.
 *
 * The scanner itself knows no dialect: which characters open a comment, how an
 * identifier is quoted, what a parameter looks like, whether a backslash
 * escapes inside a string — all of it is answered from here. A database
 * package builds one of these, and it is checked as it is built rather than
 * relied on to be usable while scanning.
 */
final class SqlLexerProfile
{
    /** @var list<non-empty-string> */
    private readonly array $lineCommentPrefixes;

    /** @var list<non-empty-string> */
    private readonly array $whitespaceDelimitedLineCommentPrefixes;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $blockCommentPairs;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $stringQuotePairs;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $identifierQuotePairs;

    /** @var array<non-empty-string, list<non-empty-string>> */
    private readonly array $namedParameterSeparators;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $namedParameterSuffixPatterns;

    /** @var array<non-empty-string, list<non-empty-string>> */
    private readonly array $namedParameterForbiddenPredecessors;

    /** @var list<non-empty-string> */
    private readonly array $backslashEscapedStringPrefixes;

    /** @var list<non-empty-string> */
    private readonly array $positionalParameterPatterns;

    /** @var array{non-empty-string, non-empty-string}|null */
    private readonly ?array $bracketPair;

    /** @var array{non-empty-string, non-empty-string} */
    private readonly array $nestingPair;

    /**
     * @param list<string> $lineCommentPrefixes
     * @param list<string> $whitespaceDelimitedLineCommentPrefixes
     * @param array<string, string> $blockCommentPairs
     * @param array<string, string> $stringQuotePairs
     * @param array<string, string> $identifierQuotePairs
     * @param array<string, list<string>> $namedParameterSeparators
     * @param array<string, string> $namedParameterSuffixPatterns
     * @param array<string, list<string>> $namedParameterForbiddenPredecessors
     * @param list<string> $backslashEscapedStringPrefixes
     * @param list<string> $positionalParameterPatterns
     * @param list<string> $lineCommentPrefixes Prefixes that start a comment running to the end of the line
     * @param list<string> $whitespaceDelimitedLineCommentPrefixes Prefixes that do so only when whitespace follows
     * @param array<string, string> $blockCommentPairs Opening delimiter => the one that closes the comment
     * @param array<string, string> $stringQuotePairs Opening quote => the one that closes the string
     * @param array<string, string> $identifierQuotePairs Opening quote => the one that closes the identifier
     * @param array<string, list<string>> $namedParameterSeparators Parameter prefix => what may separate it from its name
     * @param array<string, string> $namedParameterSuffixPatterns Parameter prefix => pattern for what may follow its name
     * @param array<string, list<string>> $namedParameterForbiddenPredecessors Parameter prefix => what it is not a parameter after
     * @param list<string> $backslashEscapedStringPrefixes Prefixes that make the string they introduce use backslash escapes
     * @param list<string> $positionalParameterPatterns Patterns a positional parameter is written as
     * @param string|null $dollarQuoteDelimiterPattern Pattern a dollar-quoted delimiter is written as, or null where the dialect has none
     * @param string $numericLiteralPattern Pattern a number is written as
     * @param string $identifierStartPattern Pattern the first character of an identifier matches
     * @param string $identifierPartPattern Pattern every later character of an identifier matches
     * @param array{string, string}|null $bracketPair Opening and closing bracket, or null where the dialect has none
     * @param array{string, string} $nestingPair Opening and closing delimiter that nest
     * @param string $statementDelimiter Single character that ends a statement
     * @param string $listDelimiter Single character that separates list items
     * @param bool $nestedBlockComments Whether a block comment may contain another
     * @param bool $backslashEscapedStrings Whether every string uses backslash escapes
     * @param LexicalPattern $patterns Reads a regular expression against a position
     * @param LexicalDelimiters $delimiters Refuses lexical data a scanner could not use
     *
     * @throws InvalidDefinitionException When a delimiter is empty, a pattern is unreadable, or a single-character delimiter is not one character
     */
    public function __construct(
        array $lineCommentPrefixes,
        array $whitespaceDelimitedLineCommentPrefixes,
        array $blockCommentPairs,
        array $stringQuotePairs,
        array $identifierQuotePairs,
        array $namedParameterSeparators,
        array $namedParameterSuffixPatterns,
        array $namedParameterForbiddenPredecessors,
        array $backslashEscapedStringPrefixes,
        array $positionalParameterPatterns,
        private readonly ?string $dollarQuoteDelimiterPattern,
        private readonly string $numericLiteralPattern,
        private readonly string $identifierStartPattern,
        private readonly string $identifierPartPattern,
        ?array $bracketPair,
        array $nestingPair,
        private readonly string $statementDelimiter,
        private readonly string $listDelimiter,
        private readonly bool $nestedBlockComments,
        private readonly bool $backslashEscapedStrings,
        private readonly LexicalPattern $patterns = new LexicalPattern(),
        LexicalDelimiters $delimiters = new LexicalDelimiters(),
    ) {
        $this->lineCommentPrefixes = $delimiters->nonEmpty($lineCommentPrefixes);
        $this->whitespaceDelimitedLineCommentPrefixes = $delimiters->nonEmpty(
            $whitespaceDelimitedLineCommentPrefixes,
        );
        $this->blockCommentPairs = $delimiters->pairs($blockCommentPairs, 'Block comment');
        $this->stringQuotePairs = $delimiters->pairs($stringQuotePairs, 'String quote');
        $this->identifierQuotePairs = $delimiters->pairs($identifierQuotePairs, 'Identifier quote');
        $this->namedParameterSeparators = $delimiters->perPrefixLists($namedParameterSeparators);
        $this->namedParameterSuffixPatterns = $delimiters->perPrefixPatterns($namedParameterSuffixPatterns);
        $this->namedParameterForbiddenPredecessors = $delimiters->perPrefixLists(
            $namedParameterForbiddenPredecessors,
        );
        $this->backslashEscapedStringPrefixes = $delimiters->nonEmpty($backslashEscapedStringPrefixes);
        $this->positionalParameterPatterns = $delimiters->validPatterns($positionalParameterPatterns);
        $this->patterns->assertValid($this->dollarQuoteDelimiterPattern);
        $this->patterns->assertValid($this->numericLiteralPattern);
        $this->patterns->assertValid($this->identifierStartPattern);
        $this->patterns->assertValid($this->identifierPartPattern);
        if ($bracketPair !== null && ($bracketPair[0] === '' || $bracketPair[1] === '')) {
            throw new InvalidDefinitionException('Bracket delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string}|null $bracketPair */
        $this->bracketPair = $bracketPair;
        if ($nestingPair[0] === '' || $nestingPair[1] === '') {
            throw new InvalidDefinitionException('Nesting delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string} $nestingPair */
        $this->nestingPair = $nestingPair;
        if (strlen($this->statementDelimiter) !== 1 || strlen($this->listDelimiter) !== 1) {
            throw new InvalidDefinitionException('Statement and list delimiters must be single characters.');
        }
    }

    /**
     * Reports whether a comment running to the end of the line starts here.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return bool True when one starts there
     */
    public function startsLineComment(string $sql, int $offset): bool
    {
        foreach ($this->lineCommentPrefixes as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) === 0) {
                return true;
            }
        }
        foreach ($this->whitespaceDelimitedLineCommentPrefixes as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) !== 0) {
                continue;
            }
            $following = $sql[$offset + strlen($prefix)] ?? '';
            if ($following === '' || ctype_space($following)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the block comment delimiters starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return array{non-empty-string, non-empty-string}|null The opening and closing delimiters, or null when no comment starts there
     */
    public function blockCommentAt(string $sql, int $offset): ?array
    {
        foreach ($this->blockCommentPairs as $opening => $closing) {
            if (substr_compare($sql, $opening, $offset, strlen($opening)) === 0) {
                return [$opening, $closing];
            }
        }

        return null;
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
     * Reports whether a block comment may contain another.
     *
     * @return bool True when the dialect nests them
     */
    public function supportsNestedBlockComments(): bool
    {
        return $this->nestedBlockComments;
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
     * Answers how long the positional parameter starting here is.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int Its length, or zero when none starts there
     */
    public function positionalParameterLengthAt(string $sql, int $offset): int
    {
        foreach ($this->positionalParameterPatterns as $pattern) {
            $match = $this->patterns->matchAt($pattern, $sql, $offset);
            if ($match !== null) {
                return strlen($match);
            }
        }

        return 0;
    }

    /**
     * Answers the prefix of the named parameter starting here, if any.
     *
     * A prefix is not always a parameter: some dialects write the same character
     * as part of an operator, and what comes before it is what tells them apart.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The prefix, or null when no parameter starts there
     */
    public function namedParameterPrefixAt(string $sql, int $offset): ?string
    {
        foreach (array_keys($this->namedParameterSeparators) as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) !== 0) {
                continue;
            }
            foreach ($this->namedParameterForbiddenPredecessors[$prefix] ?? [] as $forbidden) {
                if ($offset >= strlen($forbidden)
                    && substr_compare($sql, $forbidden, $offset - strlen($forbidden), strlen($forbidden)) === 0
                ) {
                    continue 2;
                }
            }

            return $prefix;
        }

        return null;
    }

    /**
     * Answers what separates a parameter prefix from its name here, if anything.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The separator, or null when none is written there
     */
    public function parameterNameSeparatorAt(string $prefix, string $sql, int $offset): ?string
    {
        foreach ($this->namedParameterSeparators[$prefix] ?? [] as $separator) {
            if (substr_compare($sql, $separator, $offset, strlen($separator)) === 0) {
                return $separator;
            }
        }

        return null;
    }

    /**
     * Answers how much a parameter written with this prefix carries after its name.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int How long it is, or zero when nothing follows
     */
    public function parameterSuffixLength(string $prefix, string $sql, int $offset): int
    {
        $match = $this->patterns->matchAt($this->namedParameterSuffixPatterns[$prefix] ?? null, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }

    /**
     * Reports whether the string opening here treats a backslash as an escape.
     *
     * Some dialects say so for every string; others only for a string introduced
     * by a particular prefix, and only where that prefix is a prefix rather than
     * the tail of an identifier.
     *
     * @param string $sql Statement being scanned
     * @param int $quoteOffset Position of the quote that opens the string
     *
     * @return bool True when a backslash escapes inside it
     */
    public function stringUsesBackslashEscapes(string $sql, int $quoteOffset): bool
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
            if ($preceding === '' || !$this->isIdentifierPart($preceding)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers how long the number starting here is.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int Its length, or zero when no number starts there
     */
    public function numberLengthAt(string $sql, int $offset): int
    {
        $match = $this->patterns->matchAt($this->numericLiteralPattern, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }

    /**
     * Reports whether an identifier may begin with this character.
     *
     * @param string $character Character to test
     *
     * @return bool True when it may
     */
    public function isIdentifierStart(string $character): bool
    {
        return $this->patterns->matchesCharacter($this->identifierStartPattern, $character);
    }

    /**
     * Reports whether an identifier may continue with this character.
     *
     * @param string $character Character to test
     *
     * @return bool True when it may
     */
    public function isIdentifierPart(string $character): bool
    {
        return $this->patterns->matchesCharacter($this->identifierPartPattern, $character);
    }

    /**
     * Reports whether this character opens a bracket.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does, and false where the dialect brackets nothing
     */
    public function isBracketOpening(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[0];
    }

    /**
     * Reports whether this character closes a bracket.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does, and false where the dialect brackets nothing
     */
    public function isBracketClosing(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[1];
    }

    /**
     * Reports whether this character opens a nesting.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does
     */
    public function isNestingOpening(string $character): bool
    {
        return $character === $this->nestingPair[0];
    }

    /**
     * Reports whether this character closes a nesting.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does
     */
    public function isNestingClosing(string $character): bool
    {
        return $character === $this->nestingPair[1];
    }

    /**
     * Reports whether this symbol ends a statement.
     *
     * @param string $symbol Symbol to test
     *
     * @return bool True when it does
     */
    public function isStatementDelimiter(string $symbol): bool
    {
        return $symbol === $this->statementDelimiter;
    }

    /**
     * Answers the character that separates list items.
     *
     * @return string The separator
     */
    public function listDelimiter(): string
    {
        return $this->listDelimiter;
    }
}
