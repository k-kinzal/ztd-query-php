<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser;
use ZtdQuery\Shadow\Mutation\UpsertExpression;

#[CoversClass(MySqlUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlUpsertExpressionParserTest extends TestCase
{
    #[DataProvider('providerMySqlExpressionCases')]
    public function testParsesMySqlExpressionCases(string $sql, mixed $expected): void
    {
        self::assertSame(
            $expected,
            (new MySqlUpsertExpressionParser())->parse($sql, 'items')->evaluate([], [], 'items'),
        );
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function providerMySqlExpressionCases(): iterable
    {
        yield 'chained or' => ['1 OR 0 OR 0', true];
        yield 'chained and' => ['1 AND 1 AND 0', false];
        yield 'equal' => ['1 = 2', false];
        yield 'bang not equal' => ['1 != 2', true];
        yield 'angle not equal' => ['1 <> 2', true];
        yield 'less' => ['1 < 2', true];
        yield 'less or equal' => ['1 <= 2', true];
        yield 'greater' => ['2 > 1', true];
        yield 'greater or equal' => ['2 >= 1', true];
        yield 'chained additive' => ['10 - 3 + 2', 9];
        yield 'chained multiplicative' => ['20 / 5 % 3 * 2', 2];
        yield 'nested unary minus' => ['- -2', 2];
        yield 'nested unary plus' => ['+ +2', 2];
        yield 'unary minus' => ['-2', -2];
        yield 'unary plus' => ['+2', 2];
        yield 'nested not' => ['NOT NOT TRUE', true];
        yield 'parenthesized precedence' => ['(1 + 2) * 3', 9];
        yield 'null' => ['NULL', null];
        yield 'false' => ['FALSE', false];
        yield 'hex integer' => ['0x10', 16];
        yield 'exponent' => ['1.5e1', 15.0];
        yield 'escaped string' => ["'it''s'", "it's"];
    }

    public function testParsesValuesAndExistingTableReferences(): void
    {
        $expression = (new MySqlUpsertExpressionParser())->parse(
            'items.quantity + VALUES(`quantity`) * 2',
            'items',
        );

        self::assertSame(11, $expression->evaluate(['quantity' => 5], ['quantity' => 3], 'items'));
    }

    public function testParsesMySqlIncomingRowAlias(): void
    {
        $expression = (new MySqlUpsertExpressionParser())->parse('new_row.quantity + 1', 'items', 'new_row');

        self::assertSame(4, $expression->evaluate(['quantity' => 5], ['quantity' => 3], 'items'));
    }

    public function testUnescapesQuotedIdentifiersAndMySqlStrings(): void
    {
        $expression = (new MySqlUpsertExpressionParser())->parse(
            "`it``ems`.`quan``tity` + VALUES(`quan``tity`)",
            'it`ems',
        );

        self::assertSame(8, $expression->evaluate(['quan`tity' => 5], ['quan`tity' => 3], 'it`ems'));
        self::assertSame(
            "a'b\\c",
            (new MySqlUpsertExpressionParser())->parse("'a\\'b\\\\c'", 'items')->evaluate([], [], 'items'),
        );
    }

    public function testParsesLiteralsPredicatesAndUnaryOperators(): void
    {
        $expression = (new MySqlUpsertExpressionParser())->parse(
            "NOT (score >= 80 AND VALUES(name) <> 'blocked')",
            'items',
        );

        self::assertTrue($expression->matches(['score' => 70], ['name' => 'ready'], 'items'));
    }

    public function testReturnsNullForUnsupportedFunction(): void
    {
        self::assertNull((new MySqlUpsertExpressionParser())->parseIfSupported('COALESCE(score, 0)', 'items'));
    }

    public function testRejectsPostgreSqlIncomingQualifier(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new MySqlUpsertExpressionParser())->parse('EXCLUDED.quantity', 'items');
    }

    #[DataProvider('providerInvalidMySqlExpression')]
    public function testRejectsInvalidMySqlExpression(string $sql): void
    {
        self::assertNull((new MySqlUpsertExpressionParser())->parseIfSupported($sql, 'items'));
    }

    /** @return iterable<string, array{string}> */
    public static function providerInvalidMySqlExpression(): iterable
    {
        yield 'empty' => [''];
        yield 'missing close parenthesis' => ['(1 + 2'];
        yield 'wrong close parenthesis' => ['(1 + 2 value'];
        yield 'extra close parenthesis' => ['1 + 2)'];
        yield 'missing additive operand' => ['1 +'];
        yield 'missing comparison operand' => ['1 ='];
        yield 'missing qualified column' => ['items.'];
        yield 'invalid qualified column' => ['items.+'];
        yield 'invalid comparison pair' => ['1 ! 2'];
        yield 'double equals' => ['1 == 1'];
        yield 'numeric underscore' => ['1_000'];
        yield 'uppercase hex prefix' => ['0X10'];
        yield 'unknown qualifier' => ['other.value'];
        yield 'empty values' => ['VALUES()'];
        yield 'values without parenthesis' => ['VALUES + 1'];
        yield 'values with wrong opening token' => ['VALUES ignored quantity )'];
        yield 'unclosed values' => ['VALUES(value'];
        yield 'values with wrong closing token' => ['VALUES(value extra'];
        yield 'non-identifier values' => ['VALUES(1)'];
    }
}
