<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Closure;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlNumberSample;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(MySqlNumberSample::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlNumberSampleTest extends TestCase
{
    /**
     * @param Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool) $draw
     */
    #[DataProvider('providerSignedIntegerRange')]
    public function testEachIntegerTypeStaysInTheRangeMysqlDeclaresForIt(
        Closure $draw,
        int $minimum,
        int $maximum,
    ): void {
        $value = $draw(new MySqlNumberSample(), Factory::create(), new ColumnDefinition('n', 'INT'));

        self::assertGreaterThanOrEqual($minimum, $value);
        self::assertLessThanOrEqual($maximum, $value);
    }

    /**
     * @return iterable<string, array{Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool), int, int}>
     */
    public static function providerSignedIntegerRange(): iterable
    {
        yield 'tinyint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int|bool => $s->tinyInt($f, $c),
            -128,
            127,
        ];
        yield 'smallint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->smallInt($f, $c),
            -32768,
            32767,
        ];
        yield 'mediumint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->mediumInt($f, $c),
            -8388608,
            8388607,
        ];
        yield 'int' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->int($f, $c),
            -2147483648,
            2147483647,
        ];
        yield 'bigint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->bigInt($f, $c),
            PHP_INT_MIN,
            PHP_INT_MAX,
        ];
    }

    /**
     * @param Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool) $draw
     */
    #[DataProvider('providerUnsignedIntegerRange')]
    public function testEachUnsignedIntegerTypeStartsAtZeroAndReachesFurther(
        Closure $draw,
        int $maximum,
    ): void {
        $column = new ColumnDefinition('n', 'INT', unsigned: true);
        $value = $draw(new MySqlNumberSample(), Factory::create(), $column);

        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual($maximum, $value);
    }

    /**
     * @return iterable<string, array{Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool), int}>
     */
    public static function providerUnsignedIntegerRange(): iterable
    {
        yield 'tinyint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int|bool => $s->tinyInt($f, $c),
            255,
        ];
        yield 'smallint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->smallInt($f, $c),
            65535,
        ];
        yield 'mediumint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->mediumInt($f, $c),
            16777215,
        ];
        yield 'int' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->int($f, $c),
            4294967295,
        ];
        yield 'bigint' => [
            static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->bigInt($f, $c),
            PHP_INT_MAX,
        ];
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

    public function testSmallIntStaysWithinTwoBytes(): void
    {
        $value = (new MySqlNumberSample())->smallInt(Factory::create(), new ColumnDefinition('n', 'SMALLINT'));

        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    public function testMediumIntStaysWithinThreeBytes(): void
    {
        $value = (new MySqlNumberSample())->mediumInt(Factory::create(), new ColumnDefinition('n', 'MEDIUMINT'));

        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    public function testIntStaysWithinFourBytes(): void
    {
        $value = (new MySqlNumberSample())->int(Factory::create(), new ColumnDefinition('n', 'INT'));

        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    public function testBigIntReachesAsFarAsPhpCanHold(): void
    {
        $value = (new MySqlNumberSample())->bigInt(Factory::create(), new ColumnDefinition('n', 'BIGINT'));

        self::assertGreaterThanOrEqual(PHP_INT_MIN, $value);
        self::assertLessThanOrEqual(PHP_INT_MAX, $value);
    }
    #[DataProvider('providerSignedTypeAndRange')]
    public function testEachSignedTypeIsDrawnFromTheRangeMysqlDeclaresForIt(Closure $draw, int $low, int $high): void
    {
        $faker = SpyGenerator::create();

        $draw(new MySqlNumberSample(), $faker, new ColumnDefinition('n', 'INT'));

        self::assertSame([[$low, $high]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool), int, int}>
     */
    public static function providerSignedTypeAndRange(): iterable
    {
        yield 'TINYINT' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int|bool => $s->tinyInt($f, $c), -128, 127];
        yield 'SMALLINT' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->smallInt($f, $c), -32768, 32767];
        yield 'MEDIUMINT' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->mediumInt($f, $c), -8388608, 8388607];
        yield 'INT' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->int($f, $c), -2147483648, 2147483647];
        yield 'BIGINT' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->bigInt($f, $c), PHP_INT_MIN, PHP_INT_MAX];
    }

    #[DataProvider('providerUnsignedTypeAndRange')]
    public function testEachUnsignedTypeIsDrawnFromZeroToWhatMysqlDeclaresForIt(Closure $draw, int $high): void
    {
        $faker = SpyGenerator::create();

        $draw(new MySqlNumberSample(), $faker, new ColumnDefinition('n', 'INT', unsigned: true));

        self::assertSame([[0, $high]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{Closure(MySqlNumberSample, Generator, ColumnDefinition): (int|bool), int}>
     */
    public static function providerUnsignedTypeAndRange(): iterable
    {
        yield 'TINYINT UNSIGNED' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int|bool => $s->tinyInt($f, $c), 255];
        yield 'SMALLINT UNSIGNED' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->smallInt($f, $c), 65535];
        yield 'MEDIUMINT UNSIGNED' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->mediumInt($f, $c), 16777215];
        yield 'INT UNSIGNED' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->int($f, $c), 4294967295];
        yield 'BIGINT UNSIGNED' => [static fn (MySqlNumberSample $s, Generator $f, ColumnDefinition $c): int => $s->bigInt($f, $c), PHP_INT_MAX];
    }

    public function testTinyIntOfWidthOneIsDrawnAsABooleanAndNothingElse(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->tinyInt($faker, new ColumnDefinition('b', 'TINYINT', length: 1));

        self::assertSame([[], [[]]], [$faker->numberBetweenCalls, $faker->methodCalls['boolean'] ?? []]);
    }

    public function testTinyIntOfWidthTwoIsDrawnAsANumberBecauseOnlyWidthOneIsABoolean(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->tinyInt($faker, new ColumnDefinition('n', 'TINYINT', length: 2));

        self::assertSame([[-128, 127]], $faker->numberBetweenCalls);
    }

    public function testDecimalBoundsAnUndeclaredColumnAtTenDigitsEitherWay(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->decimal($faker, new ColumnDefinition('d', 'DECIMAL'));

        self::assertSame([[0, -9999999999.0, 9999999999.0]], $faker->randomFloatCalls);
    }

    public function testDecimalLeavesTheDigitsAfterThePointOutOfTheBound(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->decimal($faker, new ColumnDefinition('d', 'DECIMAL', precision: 6, scale: 3));

        self::assertSame([[3, -999.0, 999.0]], $faker->randomFloatCalls);
    }

    public function testDecimalStartsAtZeroOnAnUnsignedColumn(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->decimal($faker, new ColumnDefinition('d', 'DECIMAL', precision: 5, scale: 2, unsigned: true));

        self::assertSame([[2, 0.0, 999.0]], $faker->randomFloatCalls);
    }

    public function testBitIsDrawnFromZeroToWhatTheDeclaredBitsHold(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->bit($faker, new ColumnDefinition('b', 'BIT', length: 8));

        self::assertSame([[0, 255]], $faker->numberBetweenCalls);
    }

    public function testBitHoldsOneBitWhereNoWidthIsDeclared(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlNumberSample())->bit($faker, new ColumnDefinition('b', 'BIT'));

        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }
}
