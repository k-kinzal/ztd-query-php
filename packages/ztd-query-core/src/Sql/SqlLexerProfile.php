<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use InvalidArgumentException;

/**
 * Immutable lexical data supplied by a database package to the neutral scanner.
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
     * @param array{string, string}|null $bracketPair
     * @param array{string, string} $nestingPair
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
    ) {
        $this->lineCommentPrefixes = self::nonEmptyStrings($lineCommentPrefixes);
        $this->whitespaceDelimitedLineCommentPrefixes = self::nonEmptyStrings(
            $whitespaceDelimitedLineCommentPrefixes,
        );
        $this->blockCommentPairs = self::delimiterPairs($blockCommentPairs, 'Block comment');
        $this->stringQuotePairs = self::delimiterPairs($stringQuotePairs, 'String quote');
        $this->identifierQuotePairs = self::delimiterPairs($identifierQuotePairs, 'Identifier quote');
        $this->namedParameterSeparators = self::parameterLists($namedParameterSeparators);
        $this->namedParameterSuffixPatterns = self::parameterPatterns($namedParameterSuffixPatterns);
        $this->namedParameterForbiddenPredecessors = self::parameterLists(
            $namedParameterForbiddenPredecessors,
        );
        $this->backslashEscapedStringPrefixes = self::nonEmptyStrings($backslashEscapedStringPrefixes);
        $this->positionalParameterPatterns = self::patterns($positionalParameterPatterns);
        self::assertPattern($this->dollarQuoteDelimiterPattern);
        self::assertPattern($this->numericLiteralPattern);
        self::assertPattern($this->identifierStartPattern);
        self::assertPattern($this->identifierPartPattern);
        if ($bracketPair !== null && ($bracketPair[0] === '' || $bracketPair[1] === '')) {
            throw new InvalidArgumentException('Bracket delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string}|null $bracketPair */
        $this->bracketPair = $bracketPair;
        if ($nestingPair[0] === '' || $nestingPair[1] === '') {
            throw new InvalidArgumentException('Nesting delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string} $nestingPair */
        $this->nestingPair = $nestingPair;
        if (strlen($this->statementDelimiter) !== 1 || strlen($this->listDelimiter) !== 1) {
            throw new InvalidArgumentException('Statement and list delimiters must be single characters.');
        }
    }

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
     * @return array{non-empty-string, non-empty-string}|null
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

    public function stringQuoteClosing(string $opening): ?string
    {
        return $this->stringQuotePairs[$opening] ?? null;
    }

    public function identifierQuoteClosing(string $opening): ?string
    {
        return $this->identifierQuotePairs[$opening] ?? null;
    }

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

    public function supportsNestedBlockComments(): bool
    {
        return $this->nestedBlockComments;
    }

    public function dollarQuoteDelimiterAt(string $sql, int $offset): ?string
    {
        return $this->matchAt($this->dollarQuoteDelimiterPattern, $sql, $offset);
    }

    public function positionalParameterLengthAt(string $sql, int $offset): int
    {
        foreach ($this->positionalParameterPatterns as $pattern) {
            $match = $this->matchAt($pattern, $sql, $offset);
            if ($match !== null) {
                return strlen($match);
            }
        }

        return 0;
    }

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

    public function parameterNameSeparatorAt(string $prefix, string $sql, int $offset): ?string
    {
        foreach ($this->namedParameterSeparators[$prefix] ?? [] as $separator) {
            if (substr_compare($sql, $separator, $offset, strlen($separator)) === 0) {
                return $separator;
            }
        }

        return null;
    }

    public function parameterSuffixLength(string $prefix, string $sql, int $offset): int
    {
        $match = $this->matchAt($this->namedParameterSuffixPatterns[$prefix] ?? null, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }

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

    public function numberLengthAt(string $sql, int $offset): int
    {
        $match = $this->matchAt($this->numericLiteralPattern, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }

    public function isIdentifierStart(string $character): bool
    {
        return $this->matchesCharacter($this->identifierStartPattern, $character);
    }

    public function isIdentifierPart(string $character): bool
    {
        return $this->matchesCharacter($this->identifierPartPattern, $character);
    }

    public function isBracketOpening(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[0];
    }

    public function isBracketClosing(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[1];
    }

    public function isNestingOpening(string $character): bool
    {
        return $character === $this->nestingPair[0];
    }

    public function isNestingClosing(string $character): bool
    {
        return $character === $this->nestingPair[1];
    }

    public function isStatementDelimiter(string $symbol): bool
    {
        return $symbol === $this->statementDelimiter;
    }

    public function listDelimiter(): string
    {
        return $this->listDelimiter;
    }

    private function matchesCharacter(string $pattern, string $character): bool
    {
        return $character !== '' && preg_match($pattern, $character) === 1;
    }

    private function matchAt(?string $pattern, string $subject, int $offset): ?string
    {
        if ($pattern === null || preg_match($pattern, substr($subject, $offset), $matches) !== 1) {
            return null;
        }

        return $matches[0] === '' ? null : $matches[0];
    }

    /**
     * @param list<string> $values
     * @return list<non-empty-string>
     */
    private static function nonEmptyStrings(array $values): array
    {
        foreach ($values as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('A lexical delimiter must not be empty.');
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $pairs
     * @return array<non-empty-string, non-empty-string>
     */
    private static function delimiterPairs(array $pairs, string $kind): array
    {
        foreach ($pairs as $opening => $closing) {
            if ($opening === '' || $closing === '') {
                throw new InvalidArgumentException($kind . ' delimiters must not be empty.');
            }
        }

        return $pairs;
    }

    /**
     * @param array<string, list<string>> $parameters
     * @return array<non-empty-string, list<non-empty-string>>
     */
    private static function parameterLists(array $parameters): array
    {
        foreach ($parameters as $prefix => $values) {
            if ($prefix === '') {
                throw new InvalidArgumentException('A parameter prefix must not be empty.');
            }
            $parameters[$prefix] = self::nonEmptyStrings($values);
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $patterns
     * @return array<non-empty-string, non-empty-string>
     */
    private static function parameterPatterns(array $patterns): array
    {
        foreach ($patterns as $prefix => $pattern) {
            if ($prefix === '' || $pattern === '') {
                throw new InvalidArgumentException('Parameter suffix patterns and prefixes must not be empty.');
            }
            self::assertPattern($pattern);
        }

        return $patterns;
    }

    /**
     * @param list<string> $patterns
     * @return list<non-empty-string>
     */
    private static function patterns(array $patterns): array
    {
        $patterns = self::nonEmptyStrings($patterns);
        foreach ($patterns as $pattern) {
            self::assertPattern($pattern);
        }

        return $patterns;
    }

    private static function assertPattern(?string $pattern): void
    {
        if ($pattern === null) {
            return;
        }
        set_error_handler(static function (): never {
            throw new InvalidArgumentException('A lexical pattern must be a valid non-empty regular expression.');
        });
        try {
            $valid = $pattern !== '' && preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
        if (!$valid) {
            throw new InvalidArgumentException('A lexical pattern must be a valid non-empty regular expression.');
        }
    }
}
