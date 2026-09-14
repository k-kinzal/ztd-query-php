<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\GeometryGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class GeometryGeneratorTest extends TestCase
{
    public function testGeneratePointUsesRequestedWktType(): void
    {
        self::assertStringStartsWith('POINT(', (new Subject())->generate(Factory::create(), new ColumnDefinition('shape', 'POINT')));
    }

    public function testGeneratePolygonUsesRequestedWktType(): void
    {
        self::assertStringStartsWith('POLYGON(', (new Subject())->generate(Factory::create(), new ColumnDefinition('shape', 'POLYGON')));
    }

    public function testGenerateMultipointUsesRequestedWktType(): void
    {
        self::assertStringStartsWith('MULTIPOINT(', (new Subject())->generate(Factory::create(), new ColumnDefinition('shape', 'MULTIPOINT')));
    }

    public function testGenerateGeometrycollectionUsesRequestedWktType(): void
    {
        self::assertStringStartsWith('GEOMETRYCOLLECTION(', (new Subject())->generate(Factory::create(), new ColumnDefinition('shape', 'GEOMETRYCOLLECTION')));
    }
}
