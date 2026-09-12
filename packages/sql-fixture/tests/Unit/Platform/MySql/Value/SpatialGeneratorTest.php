<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\SpatialGenerator as Subject;

#[CoversClass(Subject::class)]
final class SpatialGeneratorTest extends TestCase
{
    public function testGeneratePointProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generatePoint($faker);
        self::assertStringStartsWith('POINT(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGenerateLineStringProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateLineString($faker);
        self::assertStringStartsWith('LINESTRING(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGeneratePolygonProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generatePolygon($faker);
        self::assertStringStartsWith('POLYGON(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGenerateMultiPointProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateMultiPoint($faker);
        self::assertStringStartsWith('MULTIPOINT(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGenerateMultiLineStringProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateMultiLineString($faker);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGenerateMultiPolygonProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateMultiPolygon($faker);
        self::assertStringStartsWith('MULTIPOLYGON(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }

    public function testGenerateGeometryCollectionProducesWkt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateGeometryCollection($faker);
        self::assertStringStartsWith('GEOMETRYCOLLECTION(', $value);
        self::assertStringEndsWith(')', $value);
        self::assertMatchesRegularExpression('/-?\d+/', $value);
    }
}
