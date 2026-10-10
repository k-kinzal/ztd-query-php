<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Srid;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Srid::class)]
#[Small]
final class SridTest extends TestCase
{
    public function testRoutinesRegisterBothSpellings(): void
    {
        self::assertSame(['ST_SRID', 'SRID'], array_map(static fn ($routine): string => $routine->name, (new Srid())->routines()));
    }

    public function testValueChangesTheIdentifierWithoutChangingCoordinates(): void
    {
        $result = (new Instance())->connect()->query('SELECT ST_SRID(Point(1,2),4326), ST_SRID(ST_SRID(Point(1,2),4326))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 4326)), '4326']], $result->rows);
    }

    public function testValueChecksTheTargetBeforeARefusedGeometry(): void
    {
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("SRID value is out of range in 'st_srid'");

        (new Instance())->connect()->query("SELECT ST_SRID('',-1)");
    }

    public function testValueEvaluatesTheGeometryBeforeANullTarget(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT ST_SRID(1/0,NULL)');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1365', 'Division by 0']], $warnings->rows);
    }

    public function testValueKeepsUnknownSourceWarningsDuringStrictWrites(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $bytes = bin2hex(Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 1234567)));
        $session->query("CREATE TABLE t(a INT); INSERT INTO t SELECT ST_SRID(X'" . $bytes . "')");
        $result = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1234567']], $result->rows);
    }

    public function testLegacyReadsOnlyARecognizedHeader(): void
    {
        self::assertSame(4326, (new Srid())->legacy(pack('V', 4326) . "\xff" . pack('V', 1)));
        self::assertNull((new Srid())->legacy(pack('V', 4326)));
        self::assertNull((new Srid())->legacy(pack('V', 4326) . "\1" . pack('V', 99)));
    }
}
