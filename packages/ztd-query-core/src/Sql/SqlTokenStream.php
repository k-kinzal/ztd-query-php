<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Lossless SQL token stream for structure-aware clause operations.
 */
final class SqlTokenStream
{
    /**
     * @param list<SqlToken> $tokens
     */
    private function __construct(
        private readonly string $sql,
        private readonly array $tokens,
    ) {
    }

    public static function tokenize(
        string $sql,
        SqlTokenDialect $dialect = SqlTokenDialect::Standard,
    ): self {
        return new self($sql, self::scan($sql, $dialect));
    }

    /** @return list<SqlToken> */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /** @return list<SqlToken> */
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

    /** @return list<string> */
    public function splitStatements(): array
    {
        $statements = [];
        $start = 0;
        foreach ($this->significantTokens() as $token) {
            if ($token->kind !== SqlTokenKind::Symbol || $token->text !== ';' || !$token->isTopLevel()) {
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
    public function splitTopLevel(string $delimiter = ','): array
    {
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
     * Return every FROM clause that belongs to a SELECT at the same nesting level.
     *
     * @return list<string>
     */
    public function selectFromClauses(): array
    {
        $tokens = $this->significantTokens();
        /** @var array<int, array<int, bool>> $selectScopes */
        $selectScopes = [];
        $clauses = [];

        foreach ($tokens as $index => $token) {
            if ($token->isKeyword('SELECT')) {
                $selectScopes[$token->depth][$token->bracketDepth] = true;
                continue;
            }
            if ($token->isKeyword('UNION') || $token->isKeyword('INTERSECT') || $token->isKeyword('EXCEPT')) {
                $selectScopes[$token->depth][$token->bracketDepth] = false;
                continue;
            }
            if (!$token->isKeyword('FROM') || ($selectScopes[$token->depth][$token->bracketDepth] ?? false) !== true) {
                continue;
            }

            $end = $this->findSelectFromEnd($tokens, $token);
            $clause = trim(substr($this->sql, $token->endOffset(), $end - $token->endOffset()));
            if ($clause !== '') {
                $clauses[] = $clause;
            }
            $selectScopes[$token->depth][$token->bracketDepth] = false;
        }

        return $clauses;
    }

    /**
     * Return physical relation names used as SELECT sources in every nested scope.
     *
     * Derived tables and table-valued functions are containers or producers rather
     * than reflected relations. Their nested SELECT clauses are inspected separately.
     * Schema qualifiers are intentionally removed because table registries are keyed
     * by relation name.
     *
     * @return list<string>
     */
    public function selectTableNames(): array
    {
        $names = [];
        foreach ($this->selectFromClauses() as $clause) {
            foreach (self::tableNamesFromClause($clause) as $name) {
                $normalized = strtolower($name);
                if (!isset($names[$normalized])) {
                    $names[$normalized] = $name;
                }
            }
        }

        return array_values($names);
    }

    /** @return list<string> */
    private static function tableNamesFromClause(string $clause): array
    {
        $tokens = self::tokenize($clause)->significantTokens();
        $names = [];
        $expectSource = true;

        foreach ($tokens as $index => $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($token->isKeyword('JOIN')) {
                $expectSource = true;
                continue;
            }
            if ($token->kind === SqlTokenKind::Symbol && $token->text === ',') {
                $expectSource = true;
                continue;
            }
            if (!$expectSource) {
                continue;
            }
            if ($token->isKeyword('LATERAL') || $token->isKeyword('ONLY')) {
                continue;
            }

            $expectSource = false;
            $source = self::relationNameAt($clause, $tokens, $index);
            if ($source !== null) {
                $names[] = $source;
            }
        }

        return $names;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function relationNameAt(string $sql, array $tokens, int $index): ?string
    {
        $token = $tokens[$index];
        if ($token->isKeyword('VALUES')
            || $token->isKeyword('SELECT')
        ) {
            return null;
        }

        $component = self::identifierAt($sql, $tokens, $index);
        if ($component === null) {
            return null;
        }
        [$name, $nextIndex] = $component;

        while (($tokens[$nextIndex] ?? null)?->kind === SqlTokenKind::Symbol
            && $tokens[$nextIndex]->text === '.'
        ) {
            $component = self::identifierAt($sql, $tokens, $nextIndex + 1);
            if ($component === null) {
                break;
            }
            [$name, $nextIndex] = $component;
        }

        $next = $tokens[$nextIndex] ?? null;
        if ($next !== null && $next->kind === SqlTokenKind::Symbol && $next->text === '(') {
            return null;
        }

        return $name;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{string, int}|null
     */
    private static function identifierAt(string $sql, array $tokens, int $index): ?array
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word) {
            return [$token->text, $index + 1];
        }
        if ($token->kind === SqlTokenKind::QuotedIdentifier && strlen($token->text) >= 2) {
            $quote = $token->text[0];
            $name = substr($token->text, 1, -1);

            return [str_replace($quote . $quote, $quote, $name), $index + 1];
        }
        if ($token->kind !== SqlTokenKind::Symbol || $token->text !== '[') {
            return null;
        }

        for ($endIndex = $index; isset($tokens[$endIndex]); $endIndex++) {
            $endToken = $tokens[$endIndex];
            if ($endToken->text !== ']' || !$endToken->isTopLevel()) {
                continue;
            }
            $following = $tokens[$endIndex + 1] ?? null;
            if ($following?->text === ']' && $following->isTopLevel()) {
                $endIndex++;
                continue;
            }
            $name = substr($sql, $token->endOffset(), $endToken->offset - $token->endOffset());

            return [str_replace(']]', ']', $name), $endIndex + 1];
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

    /** @param list<SqlToken> $tokens */
    private function findSelectFromEnd(array $tokens, SqlToken $fromToken): int
    {
        $terminators = [
            ['WHERE'], ['GROUP', 'BY'], ['HAVING'], ['ORDER', 'BY'],
            ['LIMIT'], ['OFFSET'], ['UNION'], ['INTERSECT'], ['EXCEPT'],
            ['FOR'], ['RETURNING'],
        ];

        $afterFrom = false;
        foreach ($tokens as $index => $token) {
            if (!$afterFrom) {
                $afterFrom = $token === $fromToken;
                continue;
            }
            if ($token->depth < $fromToken->depth || $token->bracketDepth < $fromToken->bracketDepth) {
                return $token->offset;
            }
            if ($token->depth !== $fromToken->depth) {
                continue;
            }
            if ($token->bracketDepth !== $fromToken->bracketDepth) {
                continue;
            }
            foreach ($terminators as $sequence) {
                if ($this->matchesKeywordSequence($tokens, $index, $sequence)) {
                    return $token->offset;
                }
            }
        }

        return strlen($this->sql);
    }

    /**
     * @param list<SqlToken> $tokens
     * @param non-empty-list<string> $keywords
     */
    private function matchesKeywordSequence(
        array $tokens,
        int $index,
        array $keywords,
    ): bool {
        foreach ($keywords as $relative => $keyword) {
            $candidate = $tokens[$index + $relative] ?? null;
            if ($candidate === null) {
                return false;
            }
            if (!$candidate->isKeyword($keyword)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<SqlToken> */
    private static function scan(string $sql, SqlTokenDialect $dialect): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;
        $depth = 0;
        $bracketDepth = 0;

        while ($offset < $length) {
            $start = $offset;
            $char = $sql[$offset];
            $next = $sql[$offset + 1] ?? '';

            if (ctype_space($char)) {
                while ($offset < $length && ctype_space($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Whitespace, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === '-' && $next === '-') {
                $lineEnd = strpos($sql, "\n", $offset);
                $offset = $lineEnd === false ? $length : $lineEnd;
                $tokens[] = self::token($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($dialect === SqlTokenDialect::MySql && $char === '#') {
                $lineEnd = strpos($sql, "\n", $offset);
                $offset = $lineEnd === false ? $length : $lineEnd;
                $tokens[] = self::token($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === '/' && $next === '*') {
                $offset += 2;
                $commentDepth = 1;
                while ($commentDepth > 0) {
                    if (!isset($sql[$offset])) {
                        break;
                    }
                    $pair = substr($sql, $offset, 2);
                    if ($pair === '/*') {
                        $commentDepth++;
                        $offset += 2;
                        continue;
                    }
                    if ($pair === '*/') {
                        $commentDepth--;
                        $offset += 2;
                        continue;
                    }
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === "'") {
                $offset = self::scanQuoted($sql, $offset, "'");
                $tokens[] = self::token($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === '"' || $char === '`') {
                $offset = self::scanQuoted($sql, $offset, $char);
                $tokens[] = self::token($sql, SqlTokenKind::QuotedIdentifier, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === '$') {
                $tagLength = self::dollarTagLength(substr($sql, $offset));
                if ($tagLength !== null) {
                    $tag = substr($sql, $offset, $tagLength);
                    $end = strpos($sql, $tag, $offset + $tagLength);
                    $offset = $end === false ? $length : $end + $tagLength;
                    $tokens[] = self::token($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                    continue;
                }
                if (ctype_digit($next)) {
                    $offset++;
                    $offset += strspn($sql, '0123456789', $offset);
                    $tokens[] = self::token($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                    continue;
                }
            }

            if ($char === '?' || ($char === ':' && $next !== ':' && self::isWordStart($next))) {
                $offset++;
                while ($offset < $length && self::isWordPart($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if (self::isWordStart($char)) {
                $offset++;
                while ($offset < $length && self::isWordPart($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = self::token($sql, SqlTokenKind::Word, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if (ctype_digit($char)) {
                $offset++;
                if ($char === '0' && (($sql[$offset] ?? '') === 'x' || ($sql[$offset] ?? '') === 'X')) {
                    $offset++;
                    $offset += strspn($sql, '0123456789ABCDEFabcdef_', $offset);
                } else {
                    $offset += strspn($sql, '0123456789_', $offset);
                    if (($sql[$offset] ?? '') === '.') {
                        $offset++;
                        $offset += strspn($sql, '0123456789_', $offset);
                    }
                    if (($sql[$offset] ?? '') === 'e' || ($sql[$offset] ?? '') === 'E') {
                        $offset++;
                        if (($sql[$offset] ?? '') === '+' || ($sql[$offset] ?? '') === '-') {
                            $offset++;
                        }
                        while ($offset < $length && ctype_digit($sql[$offset])) {
                            $offset++;
                        }
                    }
                }
                $tokens[] = self::token($sql, SqlTokenKind::Number, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            }
            $offset++;
            $tokens[] = self::token($sql, SqlTokenKind::Symbol, $start, $offset, $depth, $bracketDepth);
            if ($char === '(') {
                $depth++;
            } elseif ($char === '[') {
                $bracketDepth++;
            }
        }

        return $tokens;
    }

    private static function scanQuoted(string $sql, int $offset, string $quote): int
    {
        $offset++;
        while (isset($sql[$offset])) {
            if ($sql[$offset] === $quote) {
                if (($sql[$offset + 1] ?? '') === $quote) {
                    $offset += 2;
                    continue;
                }

                return $offset + 1;
            }
            if ($sql[$offset] === '\\') {
                $offset += 2;
                continue;
            }
            $offset++;
        }

        return strlen($sql);
    }

    private static function dollarTagLength(string $tail): ?int
    {
        if (preg_match('/^\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/', $tail, $matches) !== 1) {
            return null;
        }

        return strlen($matches[0]);
    }

    private static function isWordStart(string $char): bool
    {
        return $char !== '' && ($char === '_' || ctype_alpha($char) || ord($char) >= 128);
    }

    private static function isWordPart(string $char): bool
    {
        return self::isWordStart($char) || ctype_digit($char) || $char === '$';
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
