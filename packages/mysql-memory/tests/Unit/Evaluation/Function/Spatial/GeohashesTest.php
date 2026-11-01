<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Geohashes;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Geohashes::class)]
#[Small]
final class GeohashesTest extends TestCase
{
    public function testRoutinesRegisterEncodingAndAllDecoders(): void
    {
        self::assertSame(['ST_GEOHASH', 'ST_POINTFROMGEOHASH', 'ST_LATFROMGEOHASH', 'ST_LONGFROMGEOHASH'], array_map(static fn ($routine): string => $routine->name, (new Geohashes())->routines()));
    }

    public function testEncodeStopsOnlyWhenTheCoordinatesAreExactlyRepresented(): void
    {
        $result = (new Instance())->connect()->query('SELECT ST_GEOHASH(1,2,100), ST_GEOHASH(POINT(1,2),4)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['s02equ04ven09qv80meqh1e1g', 's02e']], $result->rows);
    }

    public function testOrdinatesUsesStoredLongitudeAndLatitudeRegardlessOfExternalAxisOrder(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_GEOHASH(ST_GEOMFROMTEXT('POINT(1 2)',4326),4)")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['s01m']], $result->rows);
    }

    public function testPointChecksTheModernReferenceRangeBeforeNullPropagation(): void
    {
        $this->expectExceptionCode(1690);
        (new Instance())->connect()->query('SELECT ST_POINTFROMGEOHASH(NULL,-1)');
    }

    public function testPointValuePreservesLegacyNullPropagationBeforeReferenceBounds(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query('SELECT ST_POINTFROMGEOHASH(NULL,-1)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testCoordinateDecodesConvertedTextAndRoundsWithinTheCell(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_LATFROMGEOHASH(CONVERT('s' USING utf16)), ST_LONGFROMGEOHASH('ezs42')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['22', '-5.6']], $result->rows);
    }

    public function testDecodeRejectsMalformedTextWithTheFunctionSpecificCondition(): void
    {
        $this->expectExceptionCode(1411);
        $this->expectExceptionMessage("Incorrect geohash value: 'a' for function ST_LATFROMGEOHASH");
        (new Geohashes())->decode('a', 'ST_LATFROMGEOHASH');
    }

    public function testEncodeRetainsConversionWarningsEvenWhenAnotherOperandIsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ST_GEOHASH(NULL,NULL,'bad')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: 'bad'"]], $session->diagnostics->conditions);
    }
    public function testPointValueKeepsUserVariableConversionSilent(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @v=X'73'");
        $result = $session->query("SELECT ST_ASTEXT(ST_POINTFROMGEOHASH('s',@v))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(22 22)']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testEncodeKeepsNullRepresentationSeparateFromItsReadDomain(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @v=X'73'; SET @v=CAST(NULL AS SIGNED)");
        $result = $session->query('SELECT ST_GEOHASH(@v,4)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        $this->expectExceptionCode(3064);
        $session->query('SELECT ST_GEOHASH(0,0,@v)');
    }

}
