<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Value\ColumnGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
final class ColumnGeneratorTest extends TestCase
{
    public function testGenerateValueRespectsStringLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateValue($faker, new ColumnDefinition('code', 'VARCHAR', length: 12));
        self::assertIsString($value);
        self::assertLessThanOrEqual(12, strlen($value));
        self::assertNotSame('', $value);
    }

    public function testGenerateIntegerRespectsNumericDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateInteger($faker, new ColumnDefinition('amount', 'INTEGER', precision: 5, scale: 2));
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    public function testGenerateRealRespectsNumericDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateReal($faker, new ColumnDefinition('amount', 'DECIMAL', precision: 5, scale: 2));
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    public function testGenerateNumericRespectsNumericDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateNumeric($faker, new ColumnDefinition('amount', 'DECIMAL', precision: 5, scale: 2));
        self::assertIsNumeric($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    public function testGenerateDecimalRespectsNumericDomain(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateDecimal($faker, new ColumnDefinition('amount', 'DECIMAL', precision: 5, scale: 2));
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    public function testGenerateTextReturnsDeclaredLength(): void
    {
        $faker = Factory::create();
        $value = (new Subject())->generateText($faker, new ColumnDefinition('value', 'TEXT', length: 10));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(10, strlen($value));
    }

    public function testGenerateBlobReturnsDeclaredLength(): void
    {
        $faker = Factory::create();
        $value = (new Subject())->generateBlob($faker, new ColumnDefinition('value', 'BLOB', length: 10));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(10, strlen($value));
    }
}
