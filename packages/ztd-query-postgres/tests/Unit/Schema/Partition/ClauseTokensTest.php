<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class ClauseTokensTest extends TestCase
{
    public function testParenthesizedValues(): void
    {
        $sql = '(1, 2)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['values' => ['1', '2'], 'next' => 5], (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->parenthesizedValues($sql, $tokens, 0));
    }

    public function testClosingParenthesisIndex(): void
    {
        $sql = '(1, 2)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(4, (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->closingParenthesisIndex($tokens, 0));
    }

    public function testIsSymbol(): void
    {
        $sql = '(1, 2)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->isSymbol($tokens[0], '('));
    }

    public function testKeywordPairIndex(): void
    {
        $sql = 'PARTITION BY RANGE (id)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(0, (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->keywordPairIndex($tokens, 'PARTITION', 'BY'));
    }

    public function testKeywordIndex(): void
    {
        $sql = 'PARTITION BY RANGE (id)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(2, (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->keywordIndex($tokens, 'RANGE'));
    }

    public function testQualifiedIdentifierAt(): void
    {
        $sql = 'public."Users"';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['name' => 'Users', 'next' => 3], (new \ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens())->qualifiedIdentifierAt($stream, $tokens, 0));
    }
}
