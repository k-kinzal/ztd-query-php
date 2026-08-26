<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlColumnSample;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(PostgreSqlColumnSample::class)]
#[UsesClass(ColumnDefinition::class)]
final class PostgreSqlColumnSampleTest extends TestCase
{
    #[DataProvider('providerTypeAndPhpType')]
    public function testOfAnswersAValueOfTheKindTheTypeCallsFor(string $type, string $phpType): void
    {
        $value = (new PostgreSqlColumnSample())->of(Factory::create(), new ColumnDefinition('c', $type, length: 6));

        self::assertSame($phpType, get_debug_type($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTypeAndPhpType(): iterable
    {
        yield 'SMALLINT' => ['SMALLINT', 'int'];
        yield 'INT2' => ['INT2', 'int'];
        yield 'INTEGER' => ['INTEGER', 'int'];
        yield 'INT' => ['INT', 'int'];
        yield 'INT4' => ['INT4', 'int'];
        yield 'BIGINT' => ['BIGINT', 'int'];
        yield 'INT8' => ['INT8', 'int'];
        yield 'REAL' => ['REAL', 'float'];
        yield 'FLOAT4' => ['FLOAT4', 'float'];
        yield 'DOUBLE PRECISION' => ['DOUBLE PRECISION', 'float'];
        yield 'FLOAT8' => ['FLOAT8', 'float'];
        yield 'DECIMAL' => ['DECIMAL', 'float'];
        yield 'NUMERIC' => ['NUMERIC', 'float'];
        yield 'DEC' => ['DEC', 'float'];
        yield 'MONEY' => ['MONEY', 'float'];
        yield 'BOOLEAN' => ['BOOLEAN', 'bool'];
        yield 'BOOL' => ['BOOL', 'bool'];
        yield 'CHAR' => ['CHAR', 'string'];
        yield 'CHARACTER' => ['CHARACTER', 'string'];
        yield 'VARCHAR' => ['VARCHAR', 'string'];
        yield 'CHARACTER VARYING' => ['CHARACTER VARYING', 'string'];
        yield 'TEXT' => ['TEXT', 'string'];
        yield 'BYTEA' => ['BYTEA', 'string'];
        yield 'DATE' => ['DATE', 'string'];
        yield 'TIME' => ['TIME', 'string'];
        yield 'TIME WITHOUT TIME ZONE' => ['TIME WITHOUT TIME ZONE', 'string'];
        yield 'TIME WITH TIME ZONE' => ['TIME WITH TIME ZONE', 'string'];
        yield 'TIMETZ' => ['TIMETZ', 'string'];
        yield 'TIMESTAMP' => ['TIMESTAMP', 'string'];
        yield 'TIMESTAMP WITHOUT TIME ZONE' => ['TIMESTAMP WITHOUT TIME ZONE', 'string'];
        yield 'TIMESTAMP WITH TIME ZONE' => ['TIMESTAMP WITH TIME ZONE', 'string'];
        yield 'TIMESTAMPTZ' => ['TIMESTAMPTZ', 'string'];
        yield 'INTERVAL' => ['INTERVAL', 'string'];
        yield 'JSON' => ['JSON', 'string'];
        yield 'JSONB' => ['JSONB', 'string'];
        yield 'UUID' => ['UUID', 'string'];
        yield 'INET' => ['INET', 'string'];
        yield 'CIDR' => ['CIDR', 'string'];
        yield 'MACADDR' => ['MACADDR', 'string'];
        yield 'INTEGER_ARRAY' => ['INTEGER_ARRAY', 'string'];
        yield 'INT_ARRAY' => ['INT_ARRAY', 'string'];
        yield 'TEXT_ARRAY' => ['TEXT_ARRAY', 'string'];
        yield 'XML' => ['XML', 'string'];
        yield 'a type nothing names' => ['SOMETHING_ELSE', 'string'];
    }

    public function testDecimalFitsWithinTheDigitsBeforeThePoint(): void
    {
        $column = new ColumnDefinition('d', 'NUMERIC', precision: 5, scale: 2);
        $value = (new PostgreSqlColumnSample())->decimal(Factory::create(), $column);

        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    public function testCharFillsExactlyTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('c', 'CHARACTER', length: 4);

        self::assertSame(4, strlen((new PostgreSqlColumnSample())->char(Factory::create(), $column)));
    }

    public function testVarcharStaysWithinTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('v', 'VARCHAR', length: 6);

        self::assertLessThanOrEqual(6, strlen((new PostgreSqlColumnSample())->varchar(Factory::create(), $column)));
    }

    public function testByteaIsWrittenAsHexBehindABackslashX(): void
    {
        self::assertMatchesRegularExpression(
            '/^\\\\x[0-9a-f]+$/',
            (new PostgreSqlColumnSample())->bytea(Factory::create()),
        );
    }

    public function testIntervalIsAnAmountAndTheUnitItCounts(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d+ (days|hours|minutes|seconds|months|years)$/',
            (new PostgreSqlColumnSample())->interval(Factory::create()),
        );
    }

    public function testJsonIsAnObjectPostgresWillParse(): void
    {
        $written = (new PostgreSqlColumnSample())->json(Factory::create());

        self::assertIsArray(json_decode($written, true));
    }

    public function testIntegerArrayIsWrittenInBraces(): void
    {
        self::assertMatchesRegularExpression(
            '/^\{\d+(,\d+)*\}$/',
            (new PostgreSqlColumnSample())->integerArray(Factory::create()),
        );
    }

    public function testTextArrayQuotesEachMemberSoACommaInOneDoesNotSplitIt(): void
    {
        self::assertMatchesRegularExpression(
            '/^\{"[^"]+"(,"[^"]+")*\}$/',
            (new PostgreSqlColumnSample())->textArray(Factory::create()),
        );
    }

    public function testParagraphsSeparatesEachParagraphWithABlankLine(): void
    {
        $text = (new PostgreSqlColumnSample())->paragraphs(Factory::create(), 3);

        self::assertCount(3, explode("\n\n", $text));
    }

    public function testParagraphsDrawsAsManyAsItWasAskedFor(): void
    {
        $text = (new PostgreSqlColumnSample())->paragraphs(Factory::create(), 1);

        self::assertStringNotContainsString("\n\n", $text);
        self::assertNotSame('', $text);
    }
}
