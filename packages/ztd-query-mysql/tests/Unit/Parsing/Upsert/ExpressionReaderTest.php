<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Upsert\ExpressionReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\LiteralReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\StringLiteral::class)]
#[CoversClass(ExpressionReader::class)]
final class ExpressionReaderTest extends TestCase
{
    public function testParseOrEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('1 OR 0', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseOr('1 OR 0', 'users', null, $tokens, $index);
        self::assertSame(true, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseAndEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('1 AND 0', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseAnd('1 AND 0', 'users', null, $tokens, $index);
        self::assertSame(false, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseComparisonEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('1 < 2', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseComparison('1 < 2', 'users', null, $tokens, $index);
        self::assertSame(true, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseAdditiveEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('1 + 2 * 3', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseAdditive('1 + 2 * 3', 'users', null, $tokens, $index);
        self::assertSame(7, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseMultiplicativeEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('3 * 2', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseMultiplicative('3 * 2', 'users', null, $tokens, $index);
        self::assertSame(6, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseUnaryEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('-2', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseUnary('-2', 'users', null, $tokens, $index);
        self::assertSame(-2, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParsePrimaryEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize("'a''b'", \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parsePrimary("'a''b'", 'users', null, $tokens, $index);
        self::assertSame("a'b", $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseColumnReferenceEvaluatesTheConsumedExpression(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('users.score', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseColumnReference('users.score', 'users', null, $tokens, $index);
        self::assertSame(7, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(count($tokens), $index);
    }

    public function testParseValuesReferenceReadsIncomingColumn(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('(score)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new ExpressionReader())->parseValuesReference('(score)', $tokens, $index);
        self::assertSame(9, $expression->evaluate(['score' => 7], ['score' => 9], 'users'));
        self::assertSame(3, $index);
    }

    public function testColumnSourceDistinguishesIncomingAlias(): void
    {
        $reader = new ExpressionReader();
        self::assertSame(\ZtdQuery\Shadow\Mutation\UpsertColumnSource::Existing, $reader->columnSource('users.score', 'users', 'users', 'incoming'));
        self::assertSame(\ZtdQuery\Shadow\Mutation\UpsertColumnSource::Incoming, $reader->columnSource('incoming.score', 'incoming', 'users', 'incoming'));
    }

    public function testComparisonOperatorConsumesCompositeSymbols(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('!=', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        self::assertSame(\ZtdQuery\Shadow\Mutation\UpsertExpressionKind::NotEqual, (new ExpressionReader())->comparisonOperator('!=', $tokens, $index));
        self::assertSame(2, $index);
    }

}
