<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\NumericGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class NumericGeneratorTest extends TestCase
{
    public function testGenerateHandlesIntegerAndDecimalDeclarations(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $generator = new Subject();
        $integer = $generator->generate($faker, new ColumnDefinition('id', 'SMALLINT'));
        self::assertIsInt($integer);
        self::assertGreaterThanOrEqual(-32768, $integer);
        self::assertLessThanOrEqual(32767, $integer);
        $decimal = $generator->generate($faker, new ColumnDefinition('price', 'DECIMAL', precision: 4, scale: 2));
        self::assertIsFloat($decimal);
        self::assertLessThanOrEqual(99.99, abs($decimal));
    }
}
