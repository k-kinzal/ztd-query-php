<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Cte;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class HeaderParserTest extends TestCase
{
    public function testParseHeader(): void
    {
        $sql = 'WITH recent AS (SELECT id FROM users) SELECT * FROM recent';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['names' => ['recent'], 'statementOffset' => 38], (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->parseHeader($sql));
    }

    public function testFindAsIndex(): void
    {
        $sql = 'WITH recent AS (SELECT id FROM users) SELECT * FROM recent';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(null, (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->findAsIndex($tokens, 1));
    }

    public function testIsSymbol(): void
    {
        $sql = 'WITH recent AS (SELECT id FROM users) SELECT * FROM recent';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->isSymbol($tokens[3], '('));
    }

    public function testIdentifierName(): void
    {
        $sql = 'WITH recent AS (SELECT id FROM users) SELECT * FROM recent';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame('recent', (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->identifierName($tokens[1]));
    }
    public function testTopLevelTokensTreatsCteBodiesAsOpaqueSpans(): void
    {
        $tokens = (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->topLevelTokens('WITH x AS (SELECT 1) SELECT * FROM x');
        self::assertSame(['WITH', 'x', 'AS', '(', ')', 'SELECT', '*', 'FROM', 'x'], array_map(static fn (\ZtdQuery\Sql\SqlToken $token): string => $token->text, $tokens));
    }

    public function testBodyStartIndexSkipsMaterializationModifiers(): void
    {
        $sql = 'x AS NOT MATERIALIZED (SELECT 1)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        self::assertSame(4, (new \ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser())->bodyStartIndex($tokens, 1));
    }
}
