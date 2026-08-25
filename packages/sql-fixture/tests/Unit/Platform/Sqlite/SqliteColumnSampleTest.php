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
}
