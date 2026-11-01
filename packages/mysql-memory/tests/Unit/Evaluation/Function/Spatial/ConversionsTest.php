<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Spatial\Conversions;
use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Conversions::class)]
#[Small]
final class ConversionsTest extends TestCase
{
    public function testConvertPreservesCoordinatesAndSridAcrossCollectionCasts(): void
    {
        $point = new Geometry(1, [[1.0, 2.0]], [], 4326);
        $multi = Conversions::convert($point, 4);
        self::assertSame(4326, $multi->srid);
        self::assertEquals($point, Conversions::convert($multi, 1));
        self::assertSame($point, Conversions::convert($point, 1));
        self::assertEquals($point, Conversions::convert(Conversions::convert($point, 7), 1));
    }

    public function testConvertRefusesAnEmptyCollectionAsAPoint(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4032);
        Conversions::convert(new Geometry(7), 1);
    }

    public function testConvertChecksPolygonRingDirection(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4033);
        Conversions::convert(new Geometry(2, [[0.0, 0.0], [1.0, 1.0], [1.0, 0.0], [0.0, 0.0]]), 3);
    }
    public function testSingleRejectsMultipleMembers(): void
    {
        $point = new Geometry(1, [[1.0, 2.0]]);
        self::assertSame($point, Conversions::single(new Geometry(4, [], [$point]), 1));
        self::assertNull(Conversions::single(new Geometry(4, [], [$point, $point]), 1));
    }

    public function testLinePreservesTheOrderOfMultiPointMembers(): void
    {
        self::assertEquals(new Geometry(2, [[1.0, 2.0], [3.0, 4.0]]), Conversions::line(new Geometry(4, [], [new Geometry(1, [[1.0, 2.0]]), new Geometry(1, [[3.0, 4.0]])])));
    }

    public function testPolygonTakesItsRingsFromAMultiLine(): void
    {
        $ring = new Geometry(2, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 0.0]]);
        self::assertEquals(new Geometry(3, [], [$ring]), Conversions::polygon(new Geometry(5, [], [$ring])));
    }

    public function testLinesRefusesMultiPolygonsWithInteriorRings(): void
    {
        $ring = new Geometry(2, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 0.0]]);
        self::assertEquals(new Geometry(5, [], [$ring]), Conversions::lines(new Geometry(6, [], [new Geometry(3, [], [$ring])])));
        self::assertNull(Conversions::lines(new Geometry(6, [], [new Geometry(3, [], [$ring, $ring])])));
    }

    public function testCollectionRequiresHomogeneousMembers(): void
    {
        $point = new Geometry(1, [[1.0, 2.0]]);
        self::assertEquals(new Geometry(4, [], [$point]), Conversions::collection(new Geometry(7, [], [$point]), 4));
        self::assertNull(Conversions::collection(new Geometry(7, [], [$point, new Geometry(7)]), 4));
    }

    public function testDirectionRejectsAReversedExteriorRing(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4033);
        Conversions::direction(new Geometry(3, [], [new Geometry(2, [[0.0, 0.0], [1.0, 1.0], [1.0, 0.0], [0.0, 0.0]])]), 'LINESTRING', 'POLYGON');
    }
    public function testMultipleExpandsLineCoordinatesIntoPointMembers(): void
    {
        self::assertEquals(new Geometry(4, [], [new Geometry(1, [[1.0, 2.0]]), new Geometry(1, [[3.0, 4.0]])]), Conversions::multiple(new Geometry(2, [[1.0, 2.0], [3.0, 4.0]]), 4));
    }
}
