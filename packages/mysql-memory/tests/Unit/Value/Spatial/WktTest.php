<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wkt::class)]
#[Small]
final class WktTest extends TestCase
{
    public function testWritePreservesNestedCollectionsAndReleaseSpellings(): void
    {
        $geometry = new Geometry(7, [], [new Geometry(7), new Geometry(4, [], [new Geometry(1, [[1.0, 2.0]])])]);
        self::assertSame('GEOMETRYCOLLECTION(GEOMETRYCOLLECTION EMPTY,MULTIPOINT((1 2)))', Wkt::write($geometry));
        self::assertSame('GEOMETRYCOLLECTION(GEOMETRYCOLLECTION(),MULTIPOINT(1 2))', Wkt::write($geometry, true, false));
    }

    public function testBodyPreservesSignedZeroAndRejectsNonfiniteTextOnlyInModernReleases(): void
    {
        self::assertSame('(-0 1e20)', Wkt::body(new Geometry(1, [[-0.0, 1e20]]), false, true));
        self::assertNull(Wkt::body(new Geometry(7, [], [new Geometry(1, [[NAN, INF]])]), false, true));
        self::assertSame('(0 0)', Wkt::body(new Geometry(1, [[NAN, INF]]), true, false));
    }
}
