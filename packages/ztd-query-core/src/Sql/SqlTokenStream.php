<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Lossless SQL token stream for structure-aware clause operations.
 */
final class SqlTokenStream
{
    /**
     * Binds a stream to the tokens it was read as, and the profile it was read with.
     *
     * @param list<SqlToken> $tokens
     */public function __construct(
        private readonly string $sql,
        private readonly array $tokens,
        private readonly SqlLexerProfile $profile,
    ) {
    }

    /**
     * Tokenize.
     *
     * @param string $sql
     * @param SqlLexerProfile $profile
     * @return self
     */
    public static function tokenize(string $sql, SqlLexerProfile $profile): self
    {
        return new self($sql, self::scan($sql, $profile), $profile);
    }

    /**
     * @return list<SqlToken>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return list<SqlToken>
     */
    public function significantTokens(): array
    {
        return array_values(array_filter(
            $this->tokens,
            static fn (SqlToken $token): bool => !in_array(
                $token->kind,
                [SqlTokenKind::Whitespace, SqlTokenKind::Comment],
                true,
            ),
        ));
    }

    /**
     * Significant token before.
     *
     * @param SqlToken $anchor
     * @return ?SqlToken
     */
    public function significantTokenBefore(SqlToken $anchor): ?SqlToken
    {
        $previous = null;
        foreach ($this->significantTokens() as $token) {
            if ($token === $anchor) {
                return $previous;
            }
            $previous = $token;
        }

        return null;
    }

    /**
     * Significant token after.
     *
     * @param SqlToken $anchor
     * @return ?SqlToken
     */
    public function significantTokenAfter(SqlToken $anchor): ?SqlToken
    {
        $previous = null;
        foreach ($this->significantTokens() as $token) {
            if ($previous === $anchor) {
                return $token;
            }
            $previous = $token;
        }

        return null;
    }

    /**
     * Matching closing nesting token.
     *
     * @param SqlToken $opening
     * @return ?SqlToken
     */
    public function matchingClosingNestingToken(SqlToken $opening): ?SqlToken
    {
        if ($opening->kind !== SqlTokenKind::Symbol || !$this->profile->isNestingOpening($opening->text)) {
            return null;
        }

        $afterOpening = false;
        foreach ($this->significantTokens() as $token) {
            if (!$afterOpening) {
                $afterOpening = $token === $opening;
                continue;
            }
            if ($token->kind !== SqlTokenKind::Symbol) {
                continue;
            }
            if (!$this->profile->isNestingClosing($token->text)) {
                continue;
            }
            if ($token->depth !== $opening->depth) {
                continue;
            }
            if ($token->bracketDepth !== $opening->bracketDepth) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @return array{name: string, next: int}|null
     */
    public function identifierAt(int $index = 0): ?array
    {
        $component = self::identifierComponentAt($this->significantTokens(), $index, $this->profile);
        if ($component === null) {
            return null;
        }

        return ['name' => $component[0], 'next' => $component[1]];
    }

    /**
     * @return list<string>
     */
    public function splitStatements(): array
    {
        $statements = [];
        $start = 0;
        foreach ($this->significantTokens() as $token) {
            if ($token->kind !== SqlTokenKind::Symbol
                || !$this->profile->isStatementDelimiter($token->text)
                || !$token->isTopLevel()
            ) {
                continue;
            }
            $statement = trim(substr($this->sql, $start, $token->offset - $start));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $start = $token->endOffset();
        }

        $statement = trim(substr($this->sql, $start));
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @param non-empty-list<string> $startKeywords
     * @param list<non-empty-list<string>> $endKeywords
     */
    public function topLevelClause(array $startKeywords, array $endKeywords = []): ?string
    {
        $tokens = $this->significantTokens();
        $start = $this->findKeywordSequence($tokens, $startKeywords, 0);
        if ($start === null) {
            return null;
        }

        $contentStart = $tokens[$start + count($startKeywords) - 1]->endOffset();
        $contentEnd = strlen($this->sql);
        foreach ($endKeywords as $sequence) {
            $candidate = $this->findKeywordSequence($tokens, $sequence, $start + count($startKeywords));
            if ($candidate !== null) {
                $contentEnd = min($contentEnd, $tokens[$candidate]->offset);
            }
        }

        return trim(substr($this->sql, $contentStart, $contentEnd - $contentStart));
    }

    /**
     * @param non-empty-list<string> $anchorKeywords
     * @param non-empty-list<string> $startKeywords
     * @param list<non-empty-list<string>> $endKeywords
     */
    public function topLevelClauseAfter(
        array $anchorKeywords,
        array $startKeywords,
        array $endKeywords = [],
    ): ?string {
        $tokens = $this->significantTokens();
        $anchor = $this->findKeywordSequence($tokens, $anchorKeywords, 0);
        if ($anchor === null) {
            return null;
        }
        $start = $this->findKeywordSequence($tokens, $startKeywords, $anchor + count($anchorKeywords));
        if ($start === null) {
            return null;
        }

        $contentStart = $tokens[$start + count($startKeywords) - 1]->endOffset();
        $contentEnd = strlen($this->sql);
        foreach ($endKeywords as $sequence) {
            $candidate = $this->findKeywordSequence($tokens, $sequence, $start + count($startKeywords));
            if ($candidate !== null) {
                $contentEnd = min($contentEnd, $tokens[$candidate]->offset);
            }
        }

        return trim(substr($this->sql, $contentStart, $contentEnd - $contentStart));
    }

    /**
     * @return list<string>
     */
    public function splitTopLevel(?string $delimiter = null): array
    {
        $delimiter ??= $this->profile->listDelimiter();
        $parts = [];
        $start = 0;
        foreach ($this->significantTokens() as $token) {
            if ($token->kind !== SqlTokenKind::Symbol
                || $token->text !== $delimiter
                || !$token->isTopLevel()
            ) {
                continue;
            }
            $parts[] = trim(substr($this->sql, $start, $token->offset - $start));
            $start = $token->endOffset();
        }
        $tail = trim(substr($this->sql, $start));
        if ($tail !== '' || $parts !== []) {
            $parts[] = $tail;
        }

        return $parts;
    }

    /**
     * First top level keyword.
     *
     * @return ?string
     */
    public function firstTopLevelKeyword(): ?string
    {
        foreach ($this->significantTokens() as $token) {
            if ($token->isTopLevel() && $token->kind === SqlTokenKind::Word) {
                return strtoupper($token->text);
            }
        }

        return null;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param non-empty-list<string> $keywords
     */
    private function findKeywordSequence(array $tokens, array $keywords, int $from): ?int
    {
        $limit = count($tokens) - count($keywords);
        for ($index = $from; $index <= $limit; $index++) {
            $token = $tokens[$index];
            if (!$token->isTopLevel()) {
                continue;
            }
            foreach ($keywords as $relative => $keyword) {
                $candidate = $tokens[$index + $relative];
                if (!$candidate->isTopLevel() || !$candidate->isKeyword($keyword)) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{string, int}|null
     */
    private static function identifierComponentAt(
        array $tokens,
        int $index,
        SqlLexerProfile $profile,
    ): ?array {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word) {
            return [$token->text, $index + 1];
        }
        if ($token->kind === SqlTokenKind::QuotedIdentifier) {
            $name = $profile->quotedIdentifierValue($token->text);
            if ($name !== null) {
                return [$name, $index + 1];
            }
        }

        return null;
    }

    /** @return list<SqlToken> */
    private static function scan(string $sql, SqlLexerProfile $profile): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;
        $depth = 0;
        $bracketDepth = 0;

        while ($offset < $length) {
            $start = $offset;
            $char = $sql[$offset];
            if (ctype_space($char)) {
                while ($offset < $length && ctype_space($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Whitespace, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->startsLineComment($sql, $offset)) {
                $lineEnd = strpos($sql, "\n", $offset);
                $offset = $lineEnd === false ? $length : $lineEnd;
                $tokens[] = self::token($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $blockComment = $profile->blockCommentAt($sql, $offset);
            if ($blockComment !== null) {
                [$opening, $closing] = $blockComment;
                $offset += strlen($opening);
                $commentDepth = 1;
                while ($commentDepth > 0) {
                    if (!isset($sql[$offset])) {
                        break;
                    }
                    if ($profile->supportsNestedBlockComments()
                        && substr_compare($sql, $opening, $offset, strlen($opening)) === 0
                    ) {
                        $commentDepth++;
                        $offset += strlen($opening);
                        continue;
                    }
                    if (substr_compare($sql, $closing, $offset, strlen($closing)) === 0) {
                        $commentDepth--;
                        $offset += strlen($closing);
                        continue;
                    }
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $stringQuoteClosing = $profile->stringQuoteClosing($char);
            if ($stringQuoteClosing !== null) {
                $offset = self::scanDelimited(
                    $sql,
                    $offset,
                    $char,
                    $stringQuoteClosing,
                    $profile->stringUsesBackslashEscapes($sql, $offset),
                );
                $tokens[] = self::token($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $identifierQuoteClosing = $profile->identifierQuoteClosing($char);
            if ($identifierQuoteClosing !== null) {
                $offset = self::scanDelimited($sql, $offset, $char, $identifierQuoteClosing, false);
                $tokens[] = self::token($sql, SqlTokenKind::QuotedIdentifier, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $dollarQuoteDelimiter = $profile->dollarQuoteDelimiterAt($sql, $offset);
            if ($dollarQuoteDelimiter !== null) {
                $delimiterLength = strlen($dollarQuoteDelimiter);
                $end = strpos($sql, $dollarQuoteDelimiter, $offset + $delimiterLength);
                $offset = $end === false ? $length : $end + $delimiterLength;
                $tokens[] = self::token($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $positionalParameterLength = $profile->positionalParameterLengthAt($sql, $offset);
            if ($positionalParameterLength > 0) {
                $offset += $positionalParameterLength;
                $tokens[] = self::token($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $parameterPrefix = $profile->namedParameterPrefixAt($sql, $offset);
            if ($parameterPrefix !== null
                && $profile->isIdentifierStart($sql[$offset + strlen($parameterPrefix)] ?? '')
            ) {
                $offset += strlen($parameterPrefix);
                while ($offset < $length) {
                    if ($profile->isIdentifierPart($sql[$offset])) {
                        $offset++;
                        continue;
                    }
                    $separator = $profile->parameterNameSeparatorAt($parameterPrefix, $sql, $offset);
                    if ($separator === null
                        || !$profile->isIdentifierStart($sql[$offset + strlen($separator)] ?? '')
                    ) {
                        break;
                    }
                    $offset += strlen($separator);
                }
                $offset += $profile->parameterSuffixLength($parameterPrefix, $sql, $offset);
                $tokens[] = self::token($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->isIdentifierStart($char)) {
                $offset++;
                while ($offset < $length && $profile->isIdentifierPart($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Word, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $numberLength = $profile->numberLengthAt($sql, $offset);
            if ($numberLength > 0) {
                $offset += $numberLength;
                $tokens[] = self::token($sql, SqlTokenKind::Number, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->isNestingClosing($char)) {
                $depth = max(0, $depth - 1);
            } elseif ($profile->isBracketClosing($char)) {
                $bracketDepth = max(0, $bracketDepth - 1);
            }
            $offset++;
            $tokens[] = self::token($sql, SqlTokenKind::Symbol, $start, $offset, $depth, $bracketDepth);
            if ($profile->isNestingOpening($char)) {
                $depth++;
            } elseif ($profile->isBracketOpening($char)) {
                $bracketDepth++;
            }
        }

        return $tokens;
    }

    private static function scanDelimited(
        string $sql,
        int $offset,
        string $opening,
        string $closing,
        bool $backslashEscapes,
    ): int {
        $offset += strlen($opening);
        while (isset($sql[$offset])) {
            if (substr_compare($sql, $closing, $offset, strlen($closing)) === 0) {
                if (substr_compare($sql, $closing . $closing, $offset, strlen($closing) * 2) === 0) {
                    $offset += strlen($closing) * 2;
                    continue;
                }

                return $offset + strlen($closing);
            }
            if ($backslashEscapes && $sql[$offset] === '\\') {
                $offset += 2;
                continue;
            }
            $offset++;
        }

        return strlen($sql);
    }

    private static function token(
        string $sql,
        SqlTokenKind $kind,
        int $start,
        int $end,
        int $depth,
        int $bracketDepth,
    ): SqlToken {
        return new SqlToken($kind, substr($sql, $start, $end - $start), $start, $depth, $bracketDepth);
    }
}
