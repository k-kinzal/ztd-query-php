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
     * @param string $sql The statement, as written
     * @param list<SqlToken> $tokens The lexemes it was read as
     * @param SqlLexerProfile $profile What the dialect spells things with
     * @param SqlKeywordSequence $keywords Finds where a clause is opened
     * @param SqlIdentifierComponent $identifiers Reads a name out of the tokens
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $tokens,
        private readonly SqlLexerProfile $profile,
        private readonly SqlKeywordSequence $keywords = new SqlKeywordSequence(),
        private readonly SqlIdentifierComponent $identifiers = new SqlIdentifierComponent(),
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
        return new self($sql, (new SqlTokenScanner())->scan($sql, $profile), $profile);
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
        $component = $this->identifiers->at($this->significantTokens(), $index, $this->profile);
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
        $start = $this->keywords->positionIn($tokens, $startKeywords, 0);
        if ($start === null) {
            return null;
        }

        $contentStart = $tokens[$start + count($startKeywords) - 1]->endOffset();
        $contentEnd = strlen($this->sql);
        foreach ($endKeywords as $sequence) {
            $candidate = $this->keywords->positionIn($tokens, $sequence, $start + count($startKeywords));
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
        $anchor = $this->keywords->positionIn($tokens, $anchorKeywords, 0);
        if ($anchor === null) {
            return null;
        }
        $start = $this->keywords->positionIn($tokens, $startKeywords, $anchor + count($anchorKeywords));
        if ($start === null) {
            return null;
        }

        $contentStart = $tokens[$start + count($startKeywords) - 1]->endOffset();
        $contentEnd = strlen($this->sql);
        foreach ($endKeywords as $sequence) {
            $candidate = $this->keywords->positionIn($tokens, $sequence, $start + count($startKeywords));
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
}
