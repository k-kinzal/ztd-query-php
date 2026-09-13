<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionCursor;
use ZtdQuery\Platform\Sqlite\Parsing\Upsert\LogicalExpressionParser;
use ZtdQuery\Platform\Sqlite\SqliteLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(LogicalExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ArithmeticExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ComparisonExpressionParser::class)]
#[UsesClass(ExpressionCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionTokenDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\PrimaryExpressionParser::class)]
#[UsesClass(SqliteLexerProfile::class)]
final class LogicalExpressionParserTest extends TestCase
{
    public function testParseOrHonorsAndPrecedence(): void
    {
        $sql = 'TRUE OR FALSE AND FALSE';
        $cursor = new ExpressionCursor($sql, 'users', SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens());
        $result = (new LogicalExpressionParser($cursor))->parseOr();
        self::assertSame(true, $result->evaluate(['count' => 4], ['count' => 3], 'users'));
        self::assertCount($cursor->index, $cursor->tokens);
    }

    public function testParseAndCombinesComparisons(): void
    {
        $sql = 'count > EXCLUDED.count AND 2 < 3';
        $cursor = new ExpressionCursor($sql, 'users', SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens());
        $result = (new LogicalExpressionParser($cursor))->parseAnd();
        self::assertSame(true, $result->evaluate(['count' => 4], ['count' => 3], 'users'));
        self::assertCount($cursor->index, $cursor->tokens);
    }

}
