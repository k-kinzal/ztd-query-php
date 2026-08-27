<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlBinarySample;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(MySqlBinarySample::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlBinarySampleTest extends TestCase
{
    public function testBinaryFillsExactlyTheDeclaredLength(): void
    {
        self::assertSame(8, strlen((new MySqlBinarySample())->binary(new ColumnDefinition('b', 'BINARY', length: 8))));
    }

    public function testBinaryWithNoLengthDeclaredHoldsOneByte(): void
    {
        self::assertSame(1, strlen((new MySqlBinarySample())->binary(new ColumnDefinition('b', 'BINARY'))));
    }

    public function testVarbinaryStaysWithinTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('v', 'VARBINARY', length: 6);
        $bytes = (new MySqlBinarySample())->varbinary(Factory::create(), $column);

        self::assertGreaterThanOrEqual(1, strlen($bytes));
        self::assertLessThanOrEqual(6, strlen($bytes));
    }

    public function testBlobStaysWithinTheLengthItsTypeAllows(): void
    {
        $bytes = (new MySqlBinarySample())->blob(Factory::create(), 255);

        self::assertGreaterThanOrEqual(1, strlen($bytes));
        self::assertLessThanOrEqual(255, strlen($bytes));
    }
    public function testBinaryFillsOneByteWhereTheDeclaredLengthIsNone(): void
    {
        self::assertSame(1, strlen((new MySqlBinarySample())->binary(new ColumnDefinition('b', 'BINARY', length: 0))));
    }

    public function testVarbinaryIsDrawnFromOneByteToTheDeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlBinarySample())->varbinary($faker, new ColumnDefinition('b', 'VARBINARY', length: 64));

        self::assertSame([[1, 64]], $faker->numberBetweenCalls);
    }

    public function testVarbinaryReachesTwoHundredAndFiftyFiveBytesWhereNoLengthIsDeclared(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlBinarySample())->varbinary($faker, new ColumnDefinition('b', 'VARBINARY'));

        self::assertSame([[1, 255]], $faker->numberBetweenCalls);
    }

    public function testVarbinaryFillsOneByteWhereTheDeclaredLengthIsNone(): void
    {
        $written = (new MySqlBinarySample())->varbinary(Factory::create(), new ColumnDefinition('b', 'VARBINARY', length: 0));

        self::assertSame(1, strlen($written));
    }

    public function testBlobIsDrawnFromOneByteToTheLengthItsTypeAllows(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlBinarySample())->blob($faker, 65535);

        self::assertSame([[1, 65535]], $faker->numberBetweenCalls);
    }
}
