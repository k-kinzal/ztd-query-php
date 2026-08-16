<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqlTokenStream::class)]
final class SqlTokenStreamTest extends TestCase
{
    public function testSplitsOnlyTopLevelStatementTerminators(): void
    {
        $sql = 'SELECT \';\' AS value; SELECT $$a;b$$; /* ; */ SELECT 3';

        self::assertSame(
            ["SELECT ';' AS value", 'SELECT $$a;b$$', '/* ; */ SELECT 3'],
            SqlTokenStream::tokenize($sql)->splitStatements(),
        );
    }

    public function testClauseIgnoresNestedKeywords(): void
    {
        $sql = "UPDATE users SET label = TRIM(BOTH 'x' FROM label), amount = CAST(raw AS DECIMAL(10,2)) WHERE id IN (SELECT id FROM source WHERE active) ORDER BY id";

        $stream = SqlTokenStream::tokenize($sql);

        self::assertSame(
            "label = TRIM(BOTH 'x' FROM label), amount = CAST(raw AS DECIMAL(10,2))",
            $stream->topLevelClause(['SET'], [['FROM'], ['WHERE'], ['ORDER', 'BY'], ['LIMIT']]),
        );
        self::assertSame(
            'id IN (SELECT id FROM source WHERE active)',
            $stream->topLevelClause(['WHERE'], [['ORDER', 'BY'], ['LIMIT']]),
        );
    }

    public function testSplitsCommasOutsideParenthesesArraysAndStrings(): void
    {
        $stream = SqlTokenStream::tokenize("ARRAY[1,2], COALESCE(a, b), 'x,y', plain");

        self::assertSame(
            ['ARRAY[1,2]', 'COALESCE(a, b)', "'x,y'", 'plain'],
            $stream->splitTopLevel(),
        );
    }

    public function testTracksQuotedIdentifiersCommentsAndParameters(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT "where", `set`, ?, :name, $2 /* FROM */')->significantTokens();
        $kinds = array_map(static fn (SqlToken $token): SqlTokenKind => $token->kind, $tokens);

        self::assertContains(SqlTokenKind::QuotedIdentifier, $kinds);
        self::assertSame(3, count(array_filter($kinds, static fn (SqlTokenKind $kind): bool => $kind === SqlTokenKind::Parameter)));
    }

    public function testFindsFirstTopLevelKeywordAfterLeadingComment(): void
    {
        self::assertSame(
            'WITH',
            SqlTokenStream::tokenize('/* SELECT */ WITH data AS (SELECT 1) SELECT * FROM data')->firstTopLevelKeyword(),
        );
    }

    public function testKeepsArithmeticOperatorsOutsideNumberTokens(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1+2, 3.5e-2')->significantTokens();

        self::assertSame(
            ['SELECT', '1', '+', '2', ',', '3.5e-2'],
            array_map(static fn (SqlToken $token): string => $token->text, $tokens),
        );
    }

    public function testFindsOnlyFromClausesOwnedBySelectScopes(): void
    {
        $sql = 'SELECT EXTRACT(YEAR FROM event_date) FROM events WHERE id IN (SELECT id FROM archived) UNION SELECT id FROM current_events';

        self::assertSame(
            ['events', 'archived', 'current_events'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }
}
