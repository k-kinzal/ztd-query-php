<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\PostgreSql\Schema\DefaultExpression as Subject;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use Tests\Statement\PostgreSqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(NumericLiteral::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testEvaluateReadsConstantsAndLeavesComputedExpressions(string $expression, int|float|bool|string|null $expected): void
    {
        self::assertSame($expected, (new Subject())->evaluate(PostgreSqlStatements::expression($expression)));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaults(): array
    {
        return [
            ['42', 42],
            ['-42', -42],
            ['+7', 7],
            ['- 7', -7],
            ['9.99', 9.99],
            ['-9.99', -9.99],
            ['1_000', 1000],
            ['0x1F', 31],
            ["'hello'", 'hello'],
            ["'it''s'", "it's"],
            ["E'a\\tb'", "a\tb"],
            ['$$dollar$$', 'dollar'],
            ["'a'\n'b'", 'ab'],
            ['TRUE', true],
            ['false', false],
            ['NULL', null],
            ["'a'::text", 'a'],
            ['1::numeric(10, 2)', null],
            ['1.5::numeric', 1.5],
            ['1.5::int', null],
            ['1::double precision', 1],
            ['1.5::real', 1.5],
            ["'abcdef'::varchar(3)", null],
            ["CAST('abcdef' AS varchar(3))", null],
            ["'a'::char", null],
            ["'x'::character varying", 'x'],
            ["'active'::status", 'active'],
            ["'{}'::json", '{}'],
            ['true::boolean', true],
            ["'{1}'::int[]", null],
            ["'5'::integer", null],
            ['1::int::bigint', 1],
            ['NULL::character varying', null],
            ["'2020-01-01'::date", '2020-01-01'],
            ["-'5'", null],
            ['now()', null],
            ['CURRENT_TIMESTAMP', null],
            ['gen_random_uuid()', null],
            ['(1 + 2)', null],
            ["'a' || 'b'", null],
            ["B'101'", null],
        ];
    }

    public function testSignedAnswersOnlyASignedNumber(): void
    {
        $negated = PostgreSqlStatements::expression('-5');
        $factorial = PostgreSqlStatements::expression("@ 'x'::int");
        self::assertInstanceOf(UnaryOperation::class, $negated);
        self::assertInstanceOf(UnaryOperation::class, $factorial);

        self::assertSame(-5, (new Subject())->signed($negated));
        self::assertNull((new Subject())->signed($factorial));
    }

    public function testConstantReadsEachConstantKind(): void
    {
        $string = PostgreSqlStatements::expression("'s'");
        $bits = PostgreSqlStatements::expression("B'1'");
        self::assertInstanceOf(Constant::class, $string);
        self::assertInstanceOf(Constant::class, $bits);

        self::assertSame('s', (new Subject())->constant($string));
        self::assertNull((new Subject())->constant($bits));
    }

    public function testIsSequenceCallRecognizesNextvalCaseInsensitively(): void
    {
        self::assertTrue((new Subject())->isSequenceCall(PostgreSqlStatements::expression("nextval('s'::regclass)")));
        self::assertTrue((new Subject())->isSequenceCall(PostgreSqlStatements::expression("NEXTVAL('s')")));
        self::assertTrue((new Subject())->isSequenceCall(PostgreSqlStatements::expression("pg_catalog.nextval('s')")));
        self::assertFalse((new Subject())->isSequenceCall(PostgreSqlStatements::expression("\"NEXTVAL\"('s')")));
        self::assertFalse((new Subject())->isSequenceCall(PostgreSqlStatements::expression('now()')));
        self::assertFalse((new Subject())->isSequenceCall(PostgreSqlStatements::expression("'nextval'")));
    }

    public function testCastKeepsAValueOnlyWhenTheTargetTypeKeepsIt(): void
    {
        $kept = PostgreSqlStatements::expression("'2020-01-01'::timestamp");
        $truncated = PostgreSqlStatements::expression("'2020-01-01 10:00:00.5'::timestamp(0)");
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast::class, $kept);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast::class, $truncated);

        self::assertSame('2020-01-01', (new Subject())->cast($kept));
        self::assertNull((new Subject())->cast($truncated));
    }

    public function testKeepsTextOnlyForTypesWithoutAModifierThatCutsIt(): void
    {
        $targets = ["'a'::text", "'a'::varchar", "'a'::varchar(3)", "'a'::char", "'a'::timestamp", "'a'::timestamp(0)", "'{}'::json", "'1'::int", "'a'::my_type(2)"];
        $kept = array_map(static function (string $expression): bool {
            $cast = PostgreSqlStatements::expression($expression);
            self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast::class, $cast);

            return (new Subject())->keepsText($cast->type->designation);
        }, $targets);

        self::assertSame([true, true, false, false, true, false, true, false, false], $kept);
    }

    public function testKeepsNumberKeepsIntegersInIntegerTypesAndAnyNumberInUnconstrainedOnes(): void
    {
        $designation = static function (string $expression): \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation {
            $cast = PostgreSqlStatements::expression($expression);
            self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast::class, $cast);

            return $cast->type->designation;
        };

        self::assertTrue((new Subject())->keepsNumber($designation('1::int'), 1));
        self::assertFalse((new Subject())->keepsNumber($designation('1::int'), 1.5));
        self::assertTrue((new Subject())->keepsNumber($designation('1::real'), 1.5));
        self::assertTrue((new Subject())->keepsNumber($designation('1::numeric'), 1.5));
        self::assertFalse((new Subject())->keepsNumber($designation('1::numeric(3, 1)'), 1.5));
        self::assertTrue((new Subject())->keepsNumber($designation('1::float'), 1.5));
        self::assertFalse((new Subject())->keepsNumber($designation('1::float(3)'), 1.5));
        self::assertFalse((new Subject())->keepsNumber($designation('1::bool'), 1));
    }
}
