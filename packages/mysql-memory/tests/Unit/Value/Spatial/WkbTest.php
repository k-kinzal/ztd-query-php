<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wkb::class)]
#[Small]
final class WkbTest extends TestCase
{
    public function testWriteMatchesThePointBytesReturnedByMySql(): void
    {
        self::assertSame('000000000101000000000000000000f03f0000000000000040', bin2hex(Wkb::write(new Geometry(1, [[1.0, 2.0]]))));
    }

    public function testReadAcceptsBigEndianCoordinatesAndPreservesTheSrid(): void
    {
        $bytes = pack('V', 4326) . "\0" . pack('N', 1) . pack('EE', 1.0, 2.0);
        self::assertEquals(new Geometry(1, [[1.0, 2.0]], [], 4326), Wkb::read($bytes));
    }

    public function testReadRejectsTruncationHugeCountsInvalidHeadersAndTrailingBytes(): void
    {
        $point = Wkb::write(new Geometry(1, [[1.0, 2.0]]));
        self::assertNull(Wkb::read(''));
        self::assertNull(Wkb::read(substr($point, 0, -1)));
        self::assertNull(Wkb::read($point . 'x'));
        self::assertNull(Wkb::read(pack('V', 0) . "\2" . pack('V', 1)));
        self::assertNull(Wkb::read(pack('V', 0) . "\1" . pack('VV', 7, 4294967295)));
        self::assertNull(Wkb::read(pack('V', 0) . str_repeat("\1" . pack('VV', 7, 1), 65) . substr($point, 4)));
    }

    public function testWriteRoundTripsPolygonRingsAndNestedCollections(): void
    {
        $ring = new Geometry(2, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 0.0]]);
        $value = new Geometry(7, [], [new Geometry(3, [], [$ring]), new Geometry(4, [], [new Geometry(1, [[3.0, 4.0]])])], 4326);
        self::assertEquals($value, Wkb::read(Wkb::write($value)));
    }
    public function testBodyWritesNoSridPrefix(): void
    {
        self::assertSame('010700000000000000', bin2hex(Wkb::body(new Geometry(7))));
    }

    public function testCoordinatesWritesLittleEndianDoubles(): void
    {
        self::assertSame('000000000000f03f0000000000000040', bin2hex(Wkb::coordinates([[1.0, 2.0]])));
    }

    public function testGeometryAdvancesPastOnePayload(): void
    {
        $offset = 0;
        self::assertEquals(new Geometry(7), Wkb::geometry("\1" . pack('VV', 7, 0) . 'next', $offset, 0));
        self::assertSame(9, $offset);
    }

    public function testNumberDoesNotAdvancePastTruncatedInput(): void
    {
        $offset = 0;
        self::assertNull(Wkb::number('abc', $offset, true));
        self::assertSame(0, $offset);
        self::assertSame(123, Wkb::number(pack('N', 123), $offset, false));
        self::assertSame(4, $offset);
    }

    public function testPointsReadsACountAndCoordinatePairs(): void
    {
        $offset = 0;
        self::assertSame([[1.0, 2.0]], Wkb::points(pack('Vee', 1, 1.0, 2.0), $offset, true));
        self::assertSame(20, $offset);
    }
}
