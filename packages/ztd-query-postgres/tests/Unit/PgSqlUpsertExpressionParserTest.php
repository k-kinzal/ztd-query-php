<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\PgSqlUpsertExpressionParser;

#[CoversClass(PgSqlUpsertExpressionParser::class)]
final class PgSqlUpsertExpressionParserTest extends TestCase
{
    public function testParsesExcludedAndQuotedExistingReferences(): void
    {
        $expression = (new PgSqlUpsertExpressionParser())->parse(
            '"items"."Quantity" + EXCLUDED."Quantity" * 2',
            'items',
        );

        self::assertSame(11, $expression->evaluate(['Quantity' => 5], ['Quantity' => 3], 'items'));
    }

    public function testParsesBooleanPredicateAndSqlString(): void
    {
        $expression = (new PgSqlUpsertExpressionParser())->parse(
            "score >= 80 AND EXCLUDED.name <> 'it''s blocked'",
            'items',
        );

        self::assertTrue($expression->matches(['score' => 80], ['name' => 'ready'], 'items'));
    }

    public function testReturnsNullForUnsupportedFunction(): void
    {
        self::assertNull((new PgSqlUpsertExpressionParser())->parseIfSupported('COALESCE(score, 0)', 'items'));
    }

    public function testRejectsMySqlValuesFunction(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlUpsertExpressionParser())->parse('VALUES(quantity)', 'items');
    }

    public function testRejectsMySqlQuotedIdentifier(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlUpsertExpressionParser())->parse('EXCLUDED.`quantity`', 'items');
    }
}
