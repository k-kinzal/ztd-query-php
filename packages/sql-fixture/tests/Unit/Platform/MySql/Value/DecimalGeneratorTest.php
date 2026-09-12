<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\DecimalGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class DecimalGeneratorTest extends TestCase
{
    public function testGenerateDecimalHonorsScaleAndPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $generator = new Subject();
        $value = $generator->generateDecimal($faker, new ColumnDefinition('price', 'DECIMAL', precision: 4, scale: 2, unsigned: true));
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(99.99, $value);
        self::assertEqualsWithDelta(round($value, 2), $value, 0.000001);

    }
}
