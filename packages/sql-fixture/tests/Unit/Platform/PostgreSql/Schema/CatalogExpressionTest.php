<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogExpression as Subject;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\SqlText::class)]
final class CatalogExpressionTest extends TestCase
{
    #[DataProvider('providerCatalogDefaults')]
    public function testEvaluateInterpretsCatalogDefaults(string $expression, int|float|bool|string|null $expected): void
    {
        self::assertSame($expected, (new Subject(new PostgreSqlParser()))->evaluate($expression));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerCatalogDefaults(): array
    {
        return [
            ["'ready'::character varying", 'ready'],
            ["'{}'::jsonb", '{}'],
            ["'it''s'::text", "it's"],
            ['NULL::character varying', null],
            ['true', true],
            ['false', false],
            ['42', 42],
            ['-1', -1],
            ["'-1'::integer", '-1'],
            ['9.99', 9.99],
            ['now()', 'now()'],
            ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ["nextval('users_id_seq'::regclass)", "nextval('users_id_seq'::regclass)"],
            ['(1 + 2)', '(1 + 2)'],
            ['not an expression ((', 'not an expression (('],
            ['1, 2', '1, 2'],
            ['', ''],
        ];
    }

    public function testIsSequenceRecognizesNextvalOnly(): void
    {
        $expressions = new Subject(new PostgreSqlParser());

        self::assertTrue($expressions->isSequence("nextval('users_id_seq'::regclass)"));
        self::assertTrue($expressions->isSequence("NEXTVAL('seq')"));
        self::assertFalse($expressions->isSequence('now()'));
        self::assertFalse($expressions->isSequence("'nextval'::text"));
        self::assertFalse($expressions->isSequence('broken (('));
    }

    public function testExpressionReturnsTheSingleTargetOrNull(): void
    {
        $expressions = new Subject(new PostgreSqlParser());
        $node = $expressions->expression('SELECT 1 + 2');

        self::assertNotNull($node);
        self::assertSame('a_expr', $node->name);
        self::assertSame('1 + 2', $node->text('SELECT 1 + 2'));
        self::assertNull($expressions->expression('SELECT 1, 2'));
        self::assertNull($expressions->expression('SELECT'));
        self::assertNull($expressions->expression('SELECT 1; SELECT 2'));
    }
}
