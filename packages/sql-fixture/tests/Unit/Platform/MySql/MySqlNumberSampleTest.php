<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlNumberSample;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(MySqlNumberSample::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlNumberSampleTest extends TestCase
{
    #[DataProvider('providerSignedIntegerRange')]
    public function testEachIntegerTypeStaysInTheRangeMysqlDeclaresForIt(
        string $method,
        int $minimum,
        int $maximum,
    ): void {
        $sample = new MySqlNumberSample();
        $value = $sample->{$method}(Factory::create(), new ColumnDefinition('n', 'INT'));

        self::assertGreaterThanOrEqual($minimum, $value);
        self::assertLessThanOrEqual($maximum, $value);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerSignedIntegerRange(): iterable
    {
        yield 'tinyint' => ['tinyInt', -128, 127];
        yield 'smallint' => ['smallInt', -32768, 32767];
        yield 'mediumint' => ['mediumInt', -8388608, 8388607];
        yield 'int' => ['int', -2147483648, 2147483647];
        yield 'bigint' => ['bigInt', PHP_INT_MIN, PHP_INT_MAX];
    }

    #[DataProvider('providerUnsignedIntegerRange')]
    public function testEachUnsignedIntegerTypeStartsAtZeroAndReachesFurther(
        string $method,
        int $maximum,
    ): void {
        $sample = new MySqlNumberSample();
        $value = $sample->{$method}(Factory::create(), new ColumnDefinition('n', 'INT', unsigned: true));

        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual($maximum, $value);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerUnsignedIntegerRange(): iterable
    {
        yield 'tinyint' => ['tinyInt', 255];
        yield 'smallint' => ['smallInt', 65535];
        yield 'mediumint' => ['mediumInt', 16777215];
        yield 'int' => ['int', 4294967295];
        yield 'bigint' => ['bigInt', PHP_INT_MAX];
    }

    public function testTinyIntOfWidthOneIsABooleanBecauseMysqlHasNoOtherOne(): void
    {
        $value = (new MySqlNumberSample())->tinyInt(Factory::create(), new ColumnDefinition('b', 'TINYINT', length: 1));

        self::assertIsBool($value);
    }

    public function testDecimalFitsWithinTheDigitsBeforeThePoint(): void
    {
        $column = new ColumnDefinition('d', 'DECIMAL', precision: 5, scale: 2);
        $value = (new MySqlNumberSample())->decimal(Factory::create(), $column);

        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    public function testDecimalOnAnUnsignedColumnIsNeverNegative(): void
    {
        $column = new ColumnDefinition('d', 'DECIMAL', precision: 5, scale: 2, unsigned: true);

        self::assertGreaterThanOrEqual(0.0, (new MySqlNumberSample())->decimal(Factory::create(), $column));
    }

    public function testDecimalWithNothingDeclaredIsTenDigitsAndNoFraction(): void
    {
        $value = (new MySqlNumberSample())->decimal(Factory::create(), new ColumnDefinition('d', 'DECIMAL'));

        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
        self::assertSame($value, (float) (int) $value);
    }

    public function testBitFitsInTheDeclaredNumberOfBits(): void
    {
        $value = (new MySqlNumberSample())->bit(Factory::create(), new ColumnDefinition('b', 'BIT', length: 4));

        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(15, $value);
    }

    public function testBitWithNoWidthDeclaredHoldsOneBit(): void
    {
        $value = (new MySqlNumberSample())->bit(Factory::create(), new ColumnDefinition('b', 'BIT'));

        self::assertContains($value, [0, 1]);
    }
}
