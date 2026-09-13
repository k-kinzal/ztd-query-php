<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Sampling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class SampleTokensTest extends TestCase
{
    public function testTokenAtOffset(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertEquals(new \ZtdQuery\Sql\SqlToken(kind: \ZtdQuery\Sql\SqlTokenKind::Word, text: 'users', offset: 0, depth: 0, bracketDepth: 0), (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->tokenAtOffset($tokens, 0));

        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertSame(null, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->tokenAtOffset($tokens, 500));
    }

    public function testSampleIndexAfter(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertSame(1, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->sampleIndexAfter($tokens, $tokens[0]));
    }

    public function testIsRelationBoundary(): void
    {
        $sql = ',';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(false, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->isRelationBoundary($tokens[0]));

        $sql = 'JOIN';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->isRelationBoundary($tokens[0]));

        $sql = 'WHERE';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->isRelationBoundary($tokens[0]));

        $sql = 'name';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(false, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->isRelationBoundary($tokens[0]));
    }

    public function testIsOpeningParenthesis(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->isOpeningParenthesis($tokens[3], $tokens[0]));
    }

    public function testClosingParenthesisIndex(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertSame(5, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->closingParenthesisIndex($tokens, 3));
    }

    public function testTokenAfter(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertEquals(new \ZtdQuery\Sql\SqlToken(kind: \ZtdQuery\Sql\SqlTokenKind::Word, text: 'TABLESAMPLE', offset: 6, depth: 0, bracketDepth: 0), (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->tokenAfter($tokens, $tokens[0]));
    }

    public function testSameLevel(): void
    {
        $sql = 'users TABLESAMPLE SYSTEM (10) REPEATABLE (7)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 'users', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 5];

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens())->sameLevel($tokens[0], $tokens[1]));
    }
}
