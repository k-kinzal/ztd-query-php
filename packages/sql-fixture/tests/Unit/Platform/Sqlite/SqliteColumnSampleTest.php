<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteAffinity;
use SqlFixture\Platform\Sqlite\SqliteColumnSample;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(SqliteColumnSample::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(SqliteAffinity::class)]
final class SqliteColumnSampleTest extends TestCase
{
    #[DataProvider('providerAffinity')]
    public function testOfAnswersAValueOfTheKindTheAffinityCallsFor(string $type, string $phpType): void
    {
        $value = (new SqliteColumnSample())->of(Factory::create(), new ColumnDefinition('c', $type));

        self::assertSame($phpType, get_debug_type($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerAffinity(): iterable
    {
        yield 'integer affinity' => ['INTEGER', 'int'];
        yield 'text affinity' => ['TEXT', 'string'];
        yield 'blob affinity' => ['BLOB', 'string'];
        yield 'no type at all' => ['', 'string'];
        yield 'real affinity' => ['REAL', 'float'];
        yield 'numeric affinity' => ['DECIMAL', 'float'];
    }

    #[DataProvider('providerIntegerWidth')]
    public function testIntegerStaysInTheRangeTheDeclaredNameSuggests(
        string $type,
        int $minimum,
        int $maximum,
    ): void {
        $value = (new SqliteColumnSample())->integer(Factory::create(), new ColumnDefinition('n', $type));

        self::assertGreaterThanOrEqual($minimum, $value);
        self::assertLessThanOrEqual($maximum, $value);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerIntegerWidth(): iterable
    {
        yield 'TINYINT' => ['TINYINT', -128, 127];
        yield 'SMALLINT' => ['SMALLINT', -32768, 32767];
        yield 'INT2' => ['INT2', -32768, 32767];
        yield 'MEDIUMINT' => ['MEDIUMINT', -8388608, 8388607];
        yield 'BIGINT' => ['BIGINT', PHP_INT_MIN, PHP_INT_MAX];
        yield 'INT8' => ['INT8', PHP_INT_MIN, PHP_INT_MAX];
        yield 'plain INTEGER' => ['INTEGER', -2147483648, 2147483647];
    }

    public function testTextFillsExactlyTheLengthACharColumnDeclares(): void
    {
        $column = new ColumnDefinition('c', 'CHAR', length: 6);

        self::assertSame(6, strlen((new SqliteColumnSample())->text(Factory::create(), $column)));
    }

    public function testTextStaysWithinTheLengthAnyOtherColumnDeclares(): void
    {
        $column = new ColumnDefinition('t', 'TEXT', length: 7);

        self::assertLessThanOrEqual(7, strlen((new SqliteColumnSample())->text(Factory::create(), $column)));
    }

    public function testTextWritesMoreForTheLongerTextTypes(): void
    {
        $short = (new SqliteColumnSample())->text(Factory::create(), new ColumnDefinition('t', 'TINYTEXT'));

        self::assertLessThanOrEqual(255, strlen($short));
    }

    public function testRealHonoursADeclaredPrecisionAndScale(): void
    {
        $column = new ColumnDefinition('d', 'REAL', precision: 4, scale: 2);
        $value = (new SqliteColumnSample())->real(Factory::create(), $column);

        self::assertGreaterThanOrEqual(-99.0, $value);
        self::assertLessThanOrEqual(99.0, $value);
    }

    public function testRealKeepsAFloatSmallerThanADouble(): void
    {
        $value = (new SqliteColumnSample())->real(Factory::create(), new ColumnDefinition('f', 'FLOAT'));

        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    public function testBlobFillsExactlyTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('b', 'BLOB', length: 5);

        self::assertSame(5, strlen((new SqliteColumnSample())->blob(Factory::create(), $column)));
    }

    #[DataProvider('providerNumericType')]
    public function testNumericReadsTheDeclaredNameAgainBecauseSqliteHasNoTypeForIt(
        string $type,
        string $phpType,
    ): void {
        $value = (new SqliteColumnSample())->numeric(Factory::create(), new ColumnDefinition('c', $type));

        self::assertSame($phpType, get_debug_type($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNumericType(): iterable
    {
        yield 'BOOLEAN' => ['BOOLEAN', 'int'];
        yield 'DATETIME' => ['DATETIME', 'string'];
        yield 'TIMESTAMP' => ['TIMESTAMP', 'string'];
        yield 'DATE' => ['DATE', 'string'];
        yield 'TIME' => ['TIME', 'string'];
        yield 'DECIMAL' => ['DECIMAL', 'float'];
        yield 'NUMERIC' => ['NUMERIC', 'float'];
        yield 'anything else' => ['GEOMETRY', 'float'];
    }

    public function testNumericAnswersABooleanAsTheOneOrZeroSqliteStores(): void
    {
        $value = (new SqliteColumnSample())->numeric(Factory::create(), new ColumnDefinition('b', 'BOOLEAN'));

        self::assertContains($value, [0, 1]);
    }

    public function testDecimalFitsWithinTheDigitsBeforeThePoint(): void
    {
        $column = new ColumnDefinition('d', 'DECIMAL', precision: 5, scale: 2);
        $value = (new SqliteColumnSample())->decimal(Factory::create(), $column);

        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    public function testParagraphsSeparatesEachParagraphWithABlankLine(): void
    {
        $text = (new SqliteColumnSample())->paragraphs(Factory::create(), 3);

        self::assertCount(3, explode("\n\n", $text));
    }

    public function testParagraphsDrawsAsManyAsItWasAskedFor(): void
    {
        $text = (new SqliteColumnSample())->paragraphs(Factory::create(), 1);

        self::assertStringNotContainsString("\n\n", $text);
        self::assertNotSame('', $text);
    }
    #[DataProvider('providerIntegerNameAndRange')]
    public function testIntegerDrawsFromTheRangeTheDeclaredNameSuggests(string $type, int $low, int $high): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->integer($faker, new ColumnDefinition('n', $type));

        self::assertSame([[$low, $high]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerIntegerNameAndRange(): iterable
    {
        yield 'TINYINT' => ['TINYINT', -128, 127];
        yield 'SMALLINT' => ['SMALLINT', -32768, 32767];
        yield 'INT2' => ['INT2', -32768, 32767];
        yield 'MEDIUMINT' => ['MEDIUMINT', -8388608, 8388607];
        yield 'BIGINT' => ['BIGINT', PHP_INT_MIN, PHP_INT_MAX];
        yield 'INT8' => ['INT8', PHP_INT_MIN, PHP_INT_MAX];
        yield 'a name that suggests nothing narrower' => ['INTEGER', -2147483648, 2147483647];
    }

    public function testIntegerReadsTheDeclaredNameWhateverCaseItIsWrittenIn(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->integer($faker, new ColumnDefinition('n', 'tinyint'));

        self::assertSame([[-128, 127]], $faker->numberBetweenCalls);
    }

    public function testTextDrawsEnoughToFillADeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->text($faker, new ColumnDefinition('t', 'TEXT', length: 40));

        self::assertSame([[40]], $faker->methodCalls['text'] ?? []);
    }

    public function testTextDrawsTheShortestFakerWillGiveForAVeryNarrowColumn(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->text($faker, new ColumnDefinition('t', 'TEXT', length: 3));

        self::assertSame([[5]], $faker->methodCalls['text'] ?? []);
    }

    public function testTextDrawsTwoHundredCharactersForAVeryWideColumn(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->text($faker, new ColumnDefinition('t', 'TEXT', length: 4000));

        self::assertSame([[200]], $faker->methodCalls['text'] ?? []);
    }

    public function testTextCutsTheDrawDownToAColumnNarrowerThanFakerWillDraw(): void
    {
        $written = (new SqliteColumnSample())->text(Factory::create(), new ColumnDefinition('t', 'TEXT', length: 3));

        self::assertSame(3, strlen($written));
    }

    public function testTextFillsACharColumnEvenWhereTheNameIsWrittenInLowerCase(): void
    {
        $written = (new SqliteColumnSample())->text(Factory::create(), new ColumnDefinition('t', 'varchar', length: 9));

        self::assertSame(9, strlen($written));
    }

    public function testTextDrawsTwoHundredAndFiftyFiveCharactersForATinyText(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->text($faker, new ColumnDefinition('t', 'TINYTEXT'));

        self::assertSame([[255]], $faker->methodCalls['text'] ?? []);
    }

    #[DataProvider('providerTextNameAndParagraphCount')]
    public function testTextWritesAsManyParagraphsAsTheDeclaredNameSuggests(string $type, int $paragraphs): void
    {
        $written = (new SqliteColumnSample())->text(Factory::create(), new ColumnDefinition('t', $type));

        self::assertCount($paragraphs, explode("\n\n", $written));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTextNameAndParagraphCount(): iterable
    {
        yield 'MEDIUMTEXT' => ['MEDIUMTEXT', 3];
        yield 'LONGTEXT' => ['LONGTEXT', 5];
        yield 'CLOB' => ['CLOB', 5];
        yield 'a name that suggests nothing longer' => ['TEXT', 2];
    }

    public function testRealBoundsADeclaredPrecisionByTheDigitsBeforeThePoint(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->real($faker, new ColumnDefinition('r', 'DECIMAL', precision: 5, scale: 2));

        self::assertSame([[2, -999.0, 999.0]], $faker->randomFloatCalls);
    }

    public function testRealKeepsAFloatWithinAThousandEitherWay(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->real($faker, new ColumnDefinition('r', 'FLOAT'));

        self::assertSame([[2, -1000.0, 1000.0]], $faker->randomFloatCalls);
    }

    public function testRealGivesADoubleTheWiderRangeItsNameSuggests(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->real($faker, new ColumnDefinition('r', 'DOUBLE'));

        self::assertSame([[4, -1000000.0, 1000000.0]], $faker->randomFloatCalls);
    }

    public function testRealReadsAFloatsNameWhateverCaseItIsWrittenIn(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->real($faker, new ColumnDefinition('r', 'float'));

        self::assertSame([[2, -1000.0, 1000.0]], $faker->randomFloatCalls);
    }

    public function testRealIgnoresAPrecisionDeclaredWithoutAScale(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->real($faker, new ColumnDefinition('r', 'DOUBLE', precision: 5));

        self::assertSame([[4, -1000000.0, 1000000.0]], $faker->randomFloatCalls);
    }

    public function testBlobDrawsBetweenOneAndAThousandBytesForAnUndeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->blob($faker, new ColumnDefinition('b', 'BLOB'));

        self::assertSame([[1, 1000]], $faker->numberBetweenCalls);
    }

    public function testBlobFillsOneByteWhereTheDeclaredLengthIsNone(): void
    {
        $written = (new SqliteColumnSample())->blob(Factory::create(), new ColumnDefinition('b', 'BLOB', length: 0));

        self::assertSame(1, strlen($written));
    }

    public function testNumericWritesABooleanAsOneOrZeroAndNothingElse(): void
    {
        $sample = new SqliteColumnSample();
        $faker = Factory::create();
        $faker->seed(20260827);
        $written = array_map(
            static fn (int $draw): int|float|string => $sample->numeric($faker, new ColumnDefinition('b', 'BOOLEAN')),
            range(1, 60),
        );
        sort($written);

        self::assertSame([0, 1], array_values(array_unique($written)));
    }

    public function testNumericReadsTheDeclaredNameWhateverCaseItIsWrittenIn(): void
    {
        $written = (new SqliteColumnSample())->numeric(Factory::create(), new ColumnDefinition('d', 'date'));

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $written);
    }

    #[DataProvider('providerNumericNameAndSpelling')]
    public function testNumericWritesTheNameInTheSpellingSqliteStoresIt(string $type, string $pattern): void
    {
        $written = (new SqliteColumnSample())->numeric(Factory::create(), new ColumnDefinition('n', $type));

        self::assertMatchesRegularExpression($pattern, (string) $written);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNumericNameAndSpelling(): iterable
    {
        yield 'DATETIME' => ['DATETIME', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'TIMESTAMP' => ['TIMESTAMP', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'DATE' => ['DATE', '/^\d{4}-\d{2}-\d{2}$/'];
        yield 'TIME' => ['TIME', '/^\d{2}:\d{2}:\d{2}$/'];
        yield 'DECIMAL' => ['DECIMAL', '/^-?\d+$/'];
        yield 'NUMERIC' => ['NUMERIC', '/^-?\d+$/'];
        yield 'a name that suggests nothing else' => ['NUM', '/^-?\d+(\.\d+)?$/'];
    }

    public function testNumericFallsBackToAFloatWithinAThousandEitherWay(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->numeric($faker, new ColumnDefinition('n', 'NUM'));

        self::assertSame([[2, -1000.0, 1000.0]], $faker->randomFloatCalls);
    }

    public function testDecimalBoundsAnUndeclaredNumericAtTenDigits(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->decimal($faker, new ColumnDefinition('d', 'DECIMAL'));

        self::assertSame([[0, -9999999999.0, 9999999999.0]], $faker->randomFloatCalls);
    }

    public function testDecimalLeavesTheDigitsAfterThePointOutOfTheBound(): void
    {
        $faker = SpyGenerator::create();

        (new SqliteColumnSample())->decimal($faker, new ColumnDefinition('d', 'DECIMAL', precision: 6, scale: 3));

        self::assertSame([[3, -999.0, 999.0]], $faker->randomFloatCalls);
    }
}
