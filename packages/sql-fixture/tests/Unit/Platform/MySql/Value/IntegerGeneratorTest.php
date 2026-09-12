<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\IntegerGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class IntegerGeneratorTest extends TestCase
{
    public function testGenerateTinyIntStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateTinyInt($faker, new ColumnDefinition('n', 'TINYINT', length: null));
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-128, $value);
        self::assertLessThanOrEqual(127, $value);
    }

    public function testGenerateSmallIntStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateSmallInt($faker, new ColumnDefinition('n', 'SMALLINT', length: null));
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    public function testGenerateMediumIntStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateMediumInt($faker, new ColumnDefinition('n', 'MEDIUMINT', length: null));
        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    public function testGenerateIntStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateInt($faker, new ColumnDefinition('n', 'INT', length: null));
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    public function testGenerateBigIntStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateBigInt($faker, new ColumnDefinition('n', 'BIGINT', length: null));
        self::assertGreaterThanOrEqual(-9223372036854775808, $value);
        self::assertLessThanOrEqual(9223372036854775807, $value);
    }

    public function testGenerateBitStaysWithinDeclaredDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateBit($faker, new ColumnDefinition('n', 'BIT', length: 8));
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(255, $value);
    }
}
