<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Value\ColumnGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\DecimalGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\NumericGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\StringGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\StructuredGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\TemporalGenerator::class)]
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
}
