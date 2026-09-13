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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
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
    public function testGenerateDecimalProducesNonzeroFractionalValues(): void
    {
        $faker = Factory::create();
        $column = new ColumnDefinition('ratio', 'DECIMAL', precision: 2, scale: 2, nullable: false);
        $values = array_map(static function (int $seed) use ($faker, $column): float {
            $faker->seed($seed);
            return (new Subject())->generateDecimal($faker, $column);
        }, range(1, 20));
        self::assertGreaterThan(1, count(array_unique($values)));
        self::assertGreaterThanOrEqual(-0.99, min($values));
        self::assertLessThanOrEqual(0.99, max($values));
    }
}
