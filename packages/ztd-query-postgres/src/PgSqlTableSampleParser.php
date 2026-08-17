<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PgSqlTableSampleParser
{
    /** @return list<PgSqlTableSample> */
    public function parse(string $sql): array
    {
        $stream = SqlTokenStream::tokenize($sql);
        $tokens = $stream->significantTokens();
        $samples = [];

        foreach ($stream->selectTableReferences() as $reference) {
            $referenceToken = $this->tokenAtOffset($tokens, $reference['unqualifiedStart']);
            if ($referenceToken === null) {
                continue;
            }
            $sampleIndex = $this->sampleIndexAfter($tokens, $reference['end'], $referenceToken);
            if ($sampleIndex === null) {
                continue;
            }
            $samples[] = $this->parseSample($sql, $tokens, $reference, $sampleIndex, $referenceToken);
        }

        return $samples;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param array{name: string, start: int, unqualifiedStart: int, end: int} $reference
     */
    private function parseSample(
        string $sql,
        array $tokens,
        array $reference,
        int $sampleIndex,
        SqlToken $referenceToken,
    ): PgSqlTableSample {
        $methodToken = $tokens[$sampleIndex + 1] ?? null;
        $method = $methodToken !== null && $methodToken->kind === SqlTokenKind::Word
            ? PgSqlTableSampleMethod::tryFrom(strtoupper($methodToken->text))
            : null;
        if ($method === null) {
            throw new UnsupportedSqlException($sql, 'TABLESAMPLE method not supported for shadow relations');
        }

        $openIndex = $sampleIndex + 2;
        $open = $tokens[$openIndex] ?? null;
        if (!$open instanceof SqlToken || !$this->isOpeningParenthesis($open, $referenceToken)) {
            throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE clause');
        }
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE clause');
        }
        $close = $tokens[$closeIndex];
        $percentageSql = trim(substr($sql, $open->endOffset(), $close->offset - $open->endOffset()));
        if ($percentageSql === '' || count(SqlTokenStream::tokenize($percentageSql)->splitTopLevel()) !== 1) {
            throw new UnsupportedSqlException($sql, 'TABLESAMPLE requires one percentage expression');
        }

        $seedSql = null;
        $endOffset = $close->endOffset();
        $repeatable = $tokens[$closeIndex + 1] ?? null;
        if ($repeatable?->isKeyword('REPEATABLE') === true
            && $this->sameLevel($repeatable, $referenceToken)
        ) {
            $seedOpenIndex = $closeIndex + 2;
            $seedOpen = $tokens[$seedOpenIndex] ?? null;
            if (!$seedOpen instanceof SqlToken || !$this->isOpeningParenthesis($seedOpen, $referenceToken)) {
                throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE REPEATABLE clause');
            }
            $seedCloseIndex = $this->closingParenthesisIndex($tokens, $seedOpenIndex);
            if ($seedCloseIndex === null) {
                throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE REPEATABLE clause');
            }
            $seedClose = $tokens[$seedCloseIndex];
            $seedSql = trim(substr($sql, $seedOpen->endOffset(), $seedClose->offset - $seedOpen->endOffset()));
            if ($seedSql === '' || count(SqlTokenStream::tokenize($seedSql)->splitTopLevel()) !== 1) {
                throw new UnsupportedSqlException($sql, 'TABLESAMPLE REPEATABLE requires one seed expression');
            }
            $endOffset = $seedClose->endOffset();
        }

        $sampleToken = $tokens[$sampleIndex];
        $aliasStart = $reference['end'];
        $aliasTokens = array_values(array_filter(
            array_slice($tokens, 0, $sampleIndex),
            static fn (SqlToken $token): bool => $token->offset >= $reference['end'],
        ));
        $inheritanceMarker = $aliasTokens[0] ?? null;
        if ($inheritanceMarker !== null
            && $inheritanceMarker->kind === SqlTokenKind::Symbol
            && $inheritanceMarker->text === '*'
            && $this->sameLevel($inheritanceMarker, $referenceToken)
        ) {
            $aliasStart = $inheritanceMarker->endOffset();
        }
        $aliasSql = trim(substr($sql, $aliasStart, $sampleToken->offset - $aliasStart));

        return new PgSqlTableSample(
            $reference['name'],
            substr($sql, $reference['start'], $reference['end'] - $reference['start']),
            $aliasSql,
            $method,
            $percentageSql,
            $seedSql,
            $reference['start'],
            $endOffset,
        );
    }

    /** @param list<SqlToken> $tokens */
    private function tokenAtOffset(array $tokens, int $offset): ?SqlToken
    {
        foreach ($tokens as $token) {
            if ($token->offset === $offset) {
                return $token;
            }
        }

        return null;
    }

    /** @param list<SqlToken> $tokens */
    private function sampleIndexAfter(array $tokens, int $offset, SqlToken $referenceToken): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->offset < $offset || !$this->sameLevel($token, $referenceToken)) {
                continue;
            }
            if ($token->isKeyword('TABLESAMPLE')) {
                return $index;
            }
            if (($token->kind === SqlTokenKind::Symbol && $token->text === ',')
                || $this->isRelationBoundary($token)
            ) {
                return null;
            }
        }

        return null;
    }

    private function isRelationBoundary(SqlToken $token): bool
    {
        foreach (['JOIN', 'ON', 'USING', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'FOR', 'RETURNING'] as $keyword) {
            if ($token->isKeyword($keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isOpeningParenthesis(SqlToken $token, SqlToken $referenceToken): bool
    {
        return $token->kind === SqlTokenKind::Symbol
            && $token->text === '('
            && $this->sameLevel($token, $referenceToken);
    }

    /** @param list<SqlToken> $tokens */
    private function closingParenthesisIndex(array $tokens, int $openIndex): ?int
    {
        $open = $tokens[$openIndex] ?? null;
        if ($open === null) {
            return null;
        }
        foreach (array_slice($tokens, $openIndex + 1, null, true) as $index => $token) {
            if ($token->kind === SqlTokenKind::Symbol
                && $token->text === ')'
                && $token->depth === $open->depth
                && $token->bracketDepth === $open->bracketDepth
            ) {
                return $index;
            }
        }

        return null;
    }

    private function sameLevel(SqlToken $token, SqlToken $referenceToken): bool
    {
        return $token->depth === $referenceToken->depth
            && $token->bracketDepth === $referenceToken->bracketDepth;
    }
}
