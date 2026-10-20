<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\PostgreSql\Schema\CatalogExpression as Subject;
use SqlFixture\Platform\PostgreSql\Schema\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;

#[CoversClass(Subject::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(NumericLiteral::class)]
final class CatalogExpressionTest extends TestCase
{
    #[DataProvider('providerExpressions')]
    public function testEvaluateInterpretsCatalogDefaults(string $expression, int|float|bool|string|null $expected): void
    {
        self::assertSame($expected, (new Subject(\Tests\Statement\PostgreSqlStatements::semantics()))->evaluate($expression));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerExpressions(): array
    {
        return [
            ["'hello'::text", 'hello'],
            ["'it''s'::character varying", "it's"],
            ["'{}'::jsonb", '{}'],
            ['NULL::character varying', null],
            ['true', true],
            ['false', false],
            ['42', 42],
            ['-1', -1],
            ["'-1'::integer", null],
            ['9.99', 9.99],
            ['now()', null],
            ['CURRENT_TIMESTAMP', null],
            ['(1 + 2)', null],
            ['not an expression ((', null],
            ['1, 2', null],
            ['1 FROM t', null],
            ['', null],
        ];
    }

    public function testIsSequenceRecognizesNextvalOnly(): void
    {
        $expressions = new Subject(\Tests\Statement\PostgreSqlStatements::semantics());

        self::assertTrue($expressions->isSequence("nextval('users_id_seq'::regclass)"));
        self::assertTrue($expressions->isSequence("NEXTVAL('seq')"));
        self::assertFalse($expressions->isSequence('now()'));
        self::assertFalse($expressions->isSequence("'nextval'::text"));
        self::assertFalse($expressions->isSequence('broken (('));
    }

    public function testExpressionReturnsTheSingleTargetOrNull(): void
    {
        $expressions = new Subject(\Tests\Statement\PostgreSqlStatements::semantics());

        self::assertInstanceOf(BinaryOperation::class, $expressions->expression('1 + 2'));
        self::assertNull($expressions->expression('1, 2'));
        self::assertNull($expressions->expression(''));
        self::assertNull($expressions->expression('1; SELECT 2'));
        self::assertNull($expressions->expression('*'));
    }
}
