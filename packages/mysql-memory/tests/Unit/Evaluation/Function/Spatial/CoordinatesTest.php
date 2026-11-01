<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Coordinates;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Coordinates::class)]
#[Small]
final class CoordinatesTest extends TestCase
{
    public function testRoutinesRegisterReadersSettersAndLegacyAliases(): void
    {
        self::assertSame(['X', 'Y', 'ST_X', 'ST_Y', 'ST_LONGITUDE', 'ST_LATITUDE'], array_map(static fn ($routine): string => $routine->name, (new Coordinates())->routines()));
    }

    public function testValueReadsDeclaredAxesAndPreservesTheReferenceOnReplacement(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_X(ST_GEOMFROMTEXT('POINT(20 30)',4326)), ST_LONGITUDE(ST_GEOMFROMTEXT('POINT(20 30)',4326)), ST_ASTEXT(ST_Y(ST_GEOMFROMTEXT('POINT(20 30)',4326),40)), ST_SRID(ST_X(ST_GEOMFROMTEXT('POINT(20 30)',4326),10))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['20', '30', 'POINT(20 40)', '4326']], $result->rows);
    }

    public function testValueEvaluatesTheReplacementBeforeNullHandling(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ST_X(NULL,'bad')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'bad'"]], $session->diagnostics->conditions);
    }

    public function testReadResolvesTheReferenceBeforeMalformedGeometry(): void
    {
        $this->expectExceptionCode(3548);
        $this->expectExceptionMessage("There's no spatial reference system with SRID 1802465890.");
        (new Instance())->connect()->query("SELECT ST_X('broken')");
    }

    public function testLegacyRefusesAnUnrecognizedTypeBeforeItsByteOrder(): void
    {
        $this->expectExceptionCode(3037);
        (new Coordinates())->legacy((string) hex2bin('000000000900000000'), \SqlSemantics\Contract\GrammarRelease::MySql5744, 'X');
    }

    public function testAxisDistinguishesNamedAxesFromDeclaredOrder(): void
    {
        $point = new Geometry(1, [[30.0, 20.0]], [], 4326);
        self::assertSame(1, (new Coordinates())->axis($point, 'X', true));
        self::assertSame(0, (new Coordinates())->axis($point, 'LONGITUDE', true));
        self::assertSame(0, (new Coordinates())->axis($point, 'X', false));
    }

    public function testBoundsReportsAnInvalidInputCoordinateInItsReferenceUnits(): void
    {
        $this->expectExceptionCode(3732);
        $this->expectExceptionMessage('A parameter of function st_y contains a geometry with latitude 101.000000, which is out of range. It must be within [-100.000000, 100.000000].');
        (new Coordinates())->bounds(new Geometry(1, [[0.0, 101.0]], [], 4807), M_PI / 200, 'st_y');
    }
}
