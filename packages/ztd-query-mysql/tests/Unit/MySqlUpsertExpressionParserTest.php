<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser;
use ZtdQuery\Shadow\Mutation\UpsertExpression;

#[CoversClass(MySqlUpsertExpressionParser::class)]
final class MySqlUpsertExpressionParserTest extends TestCase
{
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
}
