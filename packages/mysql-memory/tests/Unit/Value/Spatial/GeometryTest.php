<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Geometry::class)]
#[Small]
final class GeometryTest extends TestCase
{
    public function testNameUsesTheCollectionAbbreviation(): void
    {
        self::assertSame('GEOMCOLLECTION', (new Geometry(7))->name());
        self::assertSame('POINT', (new Geometry(1))->name());
    }

    public function testValidChecksCoordinatesMemberTypesAndCardinality(): void
    {
        self::assertTrue((new Geometry(7))->valid());
        self::assertFalse((new Geometry(4))->valid());
        self::assertFalse((new Geometry(1, [[INF, 0.0]]))->valid());
        self::assertFalse((new Geometry(2, [[1.0, 2.0]]))->valid());
        self::assertTrue((new Geometry(2, [[1.0, 2.0]]))->valid(true));
        self::assertFalse((new Geometry(4, [], [new Geometry(7)]))->valid());
    }

    public function testRingRequiresFourCoordinatesAndClosure(): void
    {
        self::assertFalse((new Geometry(2, [[0.0, 0.0], [1.0, 1.0]]))->ring());
        self::assertTrue((new Geometry(2, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 0.0]]))->ring());
    }
}
