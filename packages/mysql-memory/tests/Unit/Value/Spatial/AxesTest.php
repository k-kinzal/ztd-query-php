<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Registry\SpatialCatalog;
use MySqlMemory\Value\Spatial\Axes;
use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Axes::class)]
#[Small]
final class AxesTest extends TestCase
{
    public function testLatitudeFirstReadsGeographicAxesWithoutMistakingQuotedNamesForNodes(): void
    {
        self::assertTrue(Axes::latitudeFirst((new SpatialCatalog())->attributes(4326)[2]));
        self::assertNull(Axes::latitudeFirst((new SpatialCatalog())->attributes(3857)[2]));
        self::assertNull(Axes::latitudeFirst(''));
        self::assertFalse(Axes::latitudeFirst('GEOGCS["AXIS[bogus,NORTH]",AXIS["longitude",EAST],AXIS["latitude",NORTH]]'));
    }

    public function testSwapRetainsSridAndMemberOrderAcrossNestedCoordinates(): void
    {
        $geometry = new Geometry(7, [], [new Geometry(1, [[1.0, 2.0]]), new Geometry(2, [[3.0, 4.0], [5.0, 6.0]])], 4326);
        $swapped = Axes::swap($geometry);
        self::assertSame(4326, $swapped->srid);
        self::assertSame([[2.0, 1.0]], $swapped->children[0]->points);
        self::assertSame([[4.0, 3.0], [6.0, 5.0]], $swapped->children[1]->points);
        self::assertEquals($geometry, Axes::swap($swapped));
    }
}
