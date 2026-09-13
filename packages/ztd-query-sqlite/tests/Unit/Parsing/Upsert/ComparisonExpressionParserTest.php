<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Parsing\Upsert\ComparisonExpressionParser;
use ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionCursor;
use ZtdQuery\Platform\Sqlite\SqliteLexerProfile;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ComparisonExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ArithmeticExpressionParser::class)]
#[UsesClass(ExpressionCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionTokenDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\LogicalExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\PrimaryExpressionParser::class)]
#[UsesClass(SqliteLexerProfile::class)]
final class ComparisonExpressionParserTest extends TestCase
{
    public function testParseComparisonEvaluatesNotEqual(): void
    {
        $sql = 'count <> EXCLUDED.count';
        $cursor = new ExpressionCursor($sql, 'users', SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens());
        $result = (new ComparisonExpressionParser($cursor))->parseComparison();
        self::assertSame(true, $result->evaluate(['count' => 4], ['count' => 3], 'users'));
        self::assertCount($cursor->index, $cursor->tokens);
    }

    public function testComparisonOperatorConsumesBothSymbols(): void
    {
        $cursor = new ExpressionCursor('>=', 'users', SqlTokenStream::tokenize('>=', SqliteLexerProfile::create())->significantTokens());
        self::assertSame(UpsertExpressionKind::GreaterOrEqual, (new ComparisonExpressionParser($cursor))->comparisonOperator());
        self::assertSame(2, $cursor->index);
        self::assertNull((new ComparisonExpressionParser($cursor))->comparisonOperator());
    }

}
