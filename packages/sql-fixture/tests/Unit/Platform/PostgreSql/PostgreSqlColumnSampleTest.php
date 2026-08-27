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
use Tests\Fixture\SpyGenerator;

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
    #[DataProvider('providerTypeAndSpelling')]
    public function testOfWritesTheTypeInTheSpellingPostgresReadsItFrom(string $type, string $pattern): void
    {
        $value = (new PostgreSqlColumnSample())->of(Factory::create(), new ColumnDefinition('c', $type, length: 6));

        self::assertMatchesRegularExpression($pattern, (string) $value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTypeAndSpelling(): iterable
    {
        yield 'CHAR' => ['CHAR', '/^.{6}$/'];
        yield 'CHARACTER' => ['CHARACTER', '/^.{6}$/'];
        yield 'VARCHAR' => ['VARCHAR', '/^.{1,6}$/s'];
        yield 'CHARACTER VARYING' => ['CHARACTER VARYING', '/^.{1,6}$/s'];
        yield 'TEXT' => ['TEXT', '/\n\n/'];
        yield 'BYTEA' => ['BYTEA', '/^\\\\x[0-9a-f]+$/'];
        yield 'DATE' => ['DATE', '/^\d{4}-\d{2}-\d{2}$/'];
        yield 'TIME' => ['TIME', '/^\d{2}:\d{2}:\d{2}$/'];
        yield 'TIME WITHOUT TIME ZONE' => ['TIME WITHOUT TIME ZONE', '/^\d{2}:\d{2}:\d{2}$/'];
        yield 'TIME WITH TIME ZONE' => ['TIME WITH TIME ZONE', '/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/'];
        yield 'TIMETZ' => ['TIMETZ', '/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/'];
        yield 'TIMESTAMP' => ['TIMESTAMP', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'TIMESTAMP WITHOUT TIME ZONE' => ['TIMESTAMP WITHOUT TIME ZONE', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'TIMESTAMP WITH TIME ZONE' => ['TIMESTAMP WITH TIME ZONE', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/'];
        yield 'TIMESTAMPTZ' => ['TIMESTAMPTZ', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/'];
        yield 'INTERVAL' => ['INTERVAL', '/^\d+ (days|hours|minutes|seconds|months|years)$/'];
        yield 'JSON' => ['JSON', '/^\{"key":.*,"value":\d+\}$/'];
        yield 'JSONB' => ['JSONB', '/^\{"key":.*,"value":\d+\}$/'];
        yield 'UUID' => ['UUID', '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/'];
        yield 'INET' => ['INET', '/^\d{1,3}(\.\d{1,3}){3}$/'];
        yield 'CIDR' => ['CIDR', '/^\d{1,3}(\.\d{1,3}){3}\/24$/'];
        yield 'MACADDR' => ['MACADDR', '/^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}$/'];
        yield 'INTEGER_ARRAY' => ['INTEGER_ARRAY', '/^\{\d+(,\d+)*\}$/'];
        yield 'INT_ARRAY' => ['INT_ARRAY', '/^\{\d+(,\d+)*\}$/'];
        yield 'TEXT_ARRAY' => ['TEXT_ARRAY', '/^\{"[^"]+"(,"[^"]+")*\}$/'];
        yield 'XML' => ['XML', '/^<root>.+<\/root>$/s'];
    }

    #[DataProvider('providerNumericTypeAndRange')]
    public function testOfDrawsAWholeNumberFromTheRangeTheTypeHolds(string $type, int $low, int $high): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->of($faker, new ColumnDefinition('n', $type));

        self::assertSame([[$low, $high]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerNumericTypeAndRange(): iterable
    {
        yield 'SMALLINT' => ['SMALLINT', -32768, 32767];
        yield 'INT2' => ['INT2', -32768, 32767];
        yield 'INTEGER' => ['INTEGER', -2147483648, 2147483647];
        yield 'INT' => ['INT', -2147483648, 2147483647];
        yield 'INT4' => ['INT4', -2147483648, 2147483647];
        yield 'BIGINT' => ['BIGINT', PHP_INT_MIN, PHP_INT_MAX];
        yield 'INT8' => ['INT8', PHP_INT_MIN, PHP_INT_MAX];
    }

    #[DataProvider('providerFloatTypeAndRange')]
    public function testOfDrawsAFractionalNumberToThePrecisionTheTypeCarries(string $type, int $decimals, float $low, float $high): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->of($faker, new ColumnDefinition('f', $type));

        self::assertSame([[$decimals, $low, $high]], $faker->randomFloatCalls);
    }

    /**
     * @return iterable<string, array{string, int, float, float}>
     */
    public static function providerFloatTypeAndRange(): iterable
    {
        yield 'REAL' => ['REAL', 2, -1000.0, 1000.0];
        yield 'FLOAT4' => ['FLOAT4', 2, -1000.0, 1000.0];
        yield 'DOUBLE PRECISION' => ['DOUBLE PRECISION', 4, -1000000.0, 1000000.0];
        yield 'FLOAT8' => ['FLOAT8', 4, -1000000.0, 1000000.0];
        yield 'MONEY' => ['MONEY', 2, 0.0, 99999.99];
    }

    public function testOfReadsATypeWhateverCaseItIsWrittenIn(): void
    {
        $value = (new PostgreSqlColumnSample())->of(Factory::create(), new ColumnDefinition('n', 'smallint'));

        self::assertSame('int', get_debug_type($value));
    }

    public function testOfWritesTwoParagraphsForText(): void
    {
        $value = (new PostgreSqlColumnSample())->of(Factory::create(), new ColumnDefinition('t', 'TEXT'));

        self::assertCount(2, explode("\n\n", (string) $value));
    }

    public function testOfFallsBackToFiftyCharactersOfTextForATypeNothingNames(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->of($faker, new ColumnDefinition('x', 'SOMETHING_ELSE'));

        self::assertSame([[50]], $faker->methodCalls['text'] ?? []);
    }

    public function testOfWrapsFiftyCharactersOfTextInARootElementForXml(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->of($faker, new ColumnDefinition('x', 'XML'));

        self::assertSame([[50]], $faker->methodCalls['text'] ?? []);
    }

    public function testDecimalBoundsAnUndeclaredNumericAtTenDigits(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->decimal($faker, new ColumnDefinition('d', 'NUMERIC'));

        self::assertSame([[0, -9999999999.0, 9999999999.0]], $faker->randomFloatCalls);
    }

    public function testDecimalLeavesTheDigitsAfterThePointOutOfTheBound(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->decimal($faker, new ColumnDefinition('d', 'NUMERIC', precision: 5, scale: 2));

        self::assertSame([[2, -999.0, 999.0]], $faker->randomFloatCalls);
    }

    public function testCharFillsOneCharacterWhereNoLengthIsDeclared(): void
    {
        $written = (new PostgreSqlColumnSample())->char(Factory::create(), new ColumnDefinition('c', 'CHAR'));

        self::assertSame(1, strlen($written));
    }

    public function testVarcharDrawsEnoughTextToFillTheDeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR', length: 40));

        self::assertSame([[40]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharDrawsTheShortestTextFakerWillGiveForAVeryNarrowColumn(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR', length: 3));

        self::assertSame([[5]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharCutsTheDrawDownToAColumnNarrowerThanFakerWillDraw(): void
    {
        $written = (new PostgreSqlColumnSample())->varchar(Factory::create(), new ColumnDefinition('v', 'VARCHAR', length: 3));

        self::assertLessThanOrEqual(3, strlen($written));
    }

    public function testVarcharDrawsTwoHundredCharactersForAnUndeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR'));

        self::assertSame([[200]], $faker->methodCalls['text'] ?? []);
    }

    public function testByteaDrawsBetweenOneAndAHundredBytes(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->bytea($faker);

        self::assertSame([[1, 100]], $faker->numberBetweenCalls);
    }

    public function testIntervalCountsBetweenOneAndThirtyOfItsUnit(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->interval($faker);

        self::assertSame([[1, 30]], $faker->numberBetweenCalls);
    }

    public function testIntervalIsCountedInEveryUnitPostgresKnows(): void
    {
        $sample = new PostgreSqlColumnSample();
        $faker = Factory::create();
        $faker->seed(20260827);
        $units = array_map(
            static fn (int $draw): string => explode(' ', $sample->interval($faker))[1],
            range(1, 200),
        );
        sort($units);

        self::assertSame(['days', 'hours', 'minutes', 'months', 'seconds', 'years'], array_values(array_unique($units)));
    }

    public function testJsonCarriesAKeyAndAValue(): void
    {
        $written = (new PostgreSqlColumnSample())->json(Factory::create());
        $decoded = json_decode($written, true);

        self::assertSame(['key', 'value'], is_array($decoded) ? array_keys($decoded) : []);
    }

    public function testJsonDrawsTwentyCharactersOfTextAndANumberUpToAHundred(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->json($faker);

        self::assertSame([[[20]], [[1, 100]]], [$faker->methodCalls['text'] ?? [], $faker->numberBetweenCalls]);
    }

    public function testIntegerArrayDrawsItsMembersFromOneToAThousand(): void
    {
        $faker = SpyGenerator::create();

        (new PostgreSqlColumnSample())->integerArray($faker);

        self::assertSame([[1, 5], [1, 1000]], array_values(array_unique($faker->numberBetweenCalls, SORT_REGULAR)));
    }

    public function testIntegerArrayHoldsBetweenOneAndFiveMembers(): void
    {
        $sample = new PostgreSqlColumnSample();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($sample->integerArray($faker), ',') + 1,
            range(1, 200),
        );
        sort($counts);

        self::assertSame([1, 2, 3, 4, 5], array_values(array_unique($counts)));
    }

    public function testTextArrayHoldsBetweenOneAndThreeMembers(): void
    {
        $sample = new PostgreSqlColumnSample();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($sample->textArray($faker), ',') + 1,
            range(1, 200),
        );
        sort($counts);

        self::assertSame([1, 2, 3], array_values(array_unique($counts)));
    }
}
