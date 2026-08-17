<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\SqliteUpsertExpressionParser;

#[CoversClass(SqliteUpsertExpressionParser::class)]
final class SqliteUpsertExpressionParserTest extends TestCase
{
    public function testParsesExcludedAndExistingReferences(): void
    {
        $expression = (new SqliteUpsertExpressionParser())->parse(
            '`items`.`quantity` + EXCLUDED.`quantity` * 2',
            'items',
        );

        self::assertSame(11, $expression->evaluate(['quantity' => 5], ['quantity' => 3], 'items'));
    }

    public function testParsesPredicate(): void
    {
        $expression = (new SqliteUpsertExpressionParser())->parse(
            "score >= 80 AND excluded.name <> 'blocked'",
            'items',
        );

        self::assertTrue($expression->matches(['score' => 80], ['name' => 'ready'], 'items'));
    }

    public function testReturnsNullForUnsupportedFunction(): void
    {
        self::assertNull((new SqliteUpsertExpressionParser())->parseIfSupported('COALESCE(score, 0)', 'items'));
    }

    public function testRejectsMySqlValuesFunction(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new SqliteUpsertExpressionParser())->parse('VALUES(quantity)', 'items');
    }
}
