<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parse;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Statement\PgSqlTableSample;
use ZtdQuery\Platform\Postgres\Statement\PgSqlTableSampleMethod;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql table sample parser.
 */
final class PgSqlTableSampleParser
{
    /**
     * @return list<PgSqlTableSample>
     */
    public function parse(string $sql): array
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $samples = [];

        foreach ((new PgSqlSelectRelationParser())->references($sql) as $reference) {
            $referenceToken = $this->tokenAtOffset($tokens, $reference['unqualifiedStart']);
            if ($referenceToken === null) {
                continue;
            }
            $sampleIndex = $this->sampleIndexAfter($tokens, $referenceToken);
            if ($sampleIndex === null) {
                continue;
            }
            $samples[] = $this->parseSample($sql, $tokens, $reference, $sampleIndex, $referenceToken);
        }

        return $samples;
    }

    /**
     * Reads the TABLESAMPLE written after a table, if one is.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param array{name: string, start: int, unqualifiedStart: int, end: int} $reference The reference
     * @param int $sampleIndex The sample index
     * @param SqlToken $referenceToken The reference token
     *
     * @return PgSqlTableSample What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function parseSample(
        string $sql,
        array $tokens,
        array $reference,
        int $sampleIndex,
        SqlToken $referenceToken,
    ): PgSqlTableSample {
        $methodToken = $tokens[$sampleIndex + 1] ?? null;
        $method = $methodToken instanceof SqlToken
            ? PgSqlTableSampleMethod::tryFrom(strtoupper($methodToken->text))
            : null;
        if ($method === null) {
            throw new UnsupportedSqlException($sql, 'TABLESAMPLE method not supported for shadow relations');
        }

        $openIndex = $sampleIndex + 2;
        $open = $tokens[$openIndex] ?? null;
        if (!$open instanceof SqlToken || !$this->isOpeningParenthesis($open, $referenceToken)) {
            throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE opening parenthesis');
        }
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE closing parenthesis');
        }
        $close = $tokens[$closeIndex];
        $percentageSql = trim(substr($sql, $open->endOffset(), $close->offset - $open->endOffset()));
        if ($percentageSql === '' || count(SqlTokenStream::tokenize($percentageSql, PgSqlLexerProfile::create())->splitTopLevel()) !== 1) {
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
                throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE REPEATABLE opening parenthesis');
            }
            $seedCloseIndex = $this->closingParenthesisIndex($tokens, $seedOpenIndex);
            if ($seedCloseIndex === null) {
                throw new UnsupportedSqlException($sql, 'Malformed TABLESAMPLE REPEATABLE closing parenthesis');
            }
            $seedClose = $tokens[$seedCloseIndex];
            $seedSql = trim(substr($sql, $seedOpen->endOffset(), $seedClose->offset - $seedOpen->endOffset()));
            if ($seedSql === '' || count(SqlTokenStream::tokenize($seedSql, PgSqlLexerProfile::create())->splitTopLevel()) !== 1) {
                throw new UnsupportedSqlException($sql, 'TABLESAMPLE REPEATABLE requires one seed expression');
            }
            $endOffset = $seedClose->endOffset();
        }

        $sampleToken = $tokens[$sampleIndex];
        $aliasStart = $reference['end'];
        $inheritanceMarker = $this->tokenAfter($tokens, $referenceToken);
        if ($inheritanceMarker->text === '*') {
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

    /**
     * Answers which token is written at a position.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $offset Position to read from
     *
     * @return SqlToken|null What it answers
     */
    public function tokenAtOffset(array $tokens, int $offset): ?SqlToken
    {
        foreach ($tokens as $token) {
            if ($token->offset === $offset) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Answers where the TABLESAMPLE after a table is written.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param SqlToken $referenceToken The reference token
     *
     * @return int|null What it answers
     */
    public function sampleIndexAfter(array $tokens, SqlToken $referenceToken): ?int
    {
        $afterReference = false;
        foreach ($tokens as $index => $token) {
            if ($token === $referenceToken) {
                $afterReference = true;
                continue;
            }
            if (!$afterReference || !$this->sameLevel($token, $referenceToken)) {
                continue;
            }
            if ($token->isKeyword('TABLESAMPLE')) {
                return $index;
            }
            if ($token->text === ',' || $this->isRelationBoundary($token)) {
                return null;
            }
        }

        return null;
    }

    /**
     * Reports whether a token ends what the table's name and alias cover.
     *
     * @param SqlToken $token Token to read
     *
     * @return bool What it answers
     */
    public function isRelationBoundary(SqlToken $token): bool
    {
        foreach (['JOIN', 'ON', 'USING', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'FOR', 'RETURNING'] as $keyword) {
            if ($token->isKeyword($keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a token opens a parenthesis.
     *
     * @param SqlToken $token Token to read
     * @param SqlToken $referenceToken The reference token
     *
     * @return bool What it answers
     */
    public function isOpeningParenthesis(SqlToken $token, SqlToken $referenceToken): bool
    {
        return $token->text === '(' && $this->sameLevel($token, $referenceToken);
    }

    /**
     * Answers where the parenthesis that closes this one is written.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $openIndex The open index
     *
     * @return int|null What it answers
     */
    public function closingParenthesisIndex(array $tokens, int $openIndex): ?int
    {
        $open = $tokens[$openIndex] ?? null;
        if ($open === null) {
            return null;
        }
        $afterOpen = false;
        foreach ($tokens as $index => $token) {
            if ($token === $open) {
                $afterOpen = true;
            } elseif ($afterOpen && $token->text === ')' && $this->sameLevel($token, $open)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers the token written after this one, at the same level.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param SqlToken $referenceToken The reference token
     *
     * @return SqlToken What it answers
     */
    public function tokenAfter(array $tokens, SqlToken $referenceToken): SqlToken
    {
        $afterReference = false;
        foreach ($tokens as $token) {
            if ($afterReference) {
                return $token;
            }
            $afterReference = $token === $referenceToken;
        }

        return $referenceToken;
    }

    /**
     * Reports whether two tokens are written at the same depth.
     *
     * @param SqlToken $token Token to read
     * @param SqlToken $referenceToken The reference token
     *
     * @return bool What it answers
     */
    public function sameLevel(SqlToken $token, SqlToken $referenceToken): bool
    {
        return $token->depth === $referenceToken->depth
            && $token->bracketDepth === $referenceToken->bracketDepth;
    }
}
