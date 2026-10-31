<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Inputs;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inputs::class)]
#[Small]
final class InputsTest extends TestCase
{
    public function testRoutinesRegisterTheTextConstructorAliases(): void
    {
        self::assertCount(61, (new Inputs())->routines());
    }

    public function testValueReadsGeographicAxesAndNullBeforeOtherOperands(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_ASTEXT(ST_GEOMFROMTEXT('POINT(1 2)',4326),'axis-order=long-lat'), ST_GEOMFROMTEXT(NULL,-1,'bad')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(2 1)', null]], $result->rows);
    }

    public function testIdentifierPreservesLegacyWrappingAndDecimalRounding(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query("SELECT ST_SRID(ST_GEOMFROMTEXT('POINT(1 2)',-1)), ST_SRID(ST_GEOMFROMTEXT('POINT(1 2)',1.5))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4294967295', '2']], $result->rows);
    }

    public function testReferenceRefusesAMissingSystemBeforeMalformedOptions(): void
    {
        $this->expectExceptionCode(3548);
        $this->expectExceptionMessage("There's no spatial reference system with SRID 1234567.");
        (new Instance())->connect()->query("SELECT ST_GEOMFROMTEXT('',1234567,'bad')");
    }

    public function testCoordinatesUseTheReferenceAngularUnits(): void
    {
        $this->expectExceptionCode(3617);
        $this->expectExceptionMessage('Latitude 100.100000 is out of range in function st_geomfromtext. It must be within [-100.000000, 100.000000].');
        (new Instance())->connect()->query("SELECT ST_GEOMFROMTEXT('POINT(100.1 200)',4807)");
    }
    public function testValueReadsBinaryPayloadsAndChecksTheirReferenceBeforeNull(): void
    {
        $result = (new Instance())->connect()->query('SELECT ST_ASTEXT(ST_GEOMFROMWKB(ST_ASWKB(Point(1,2))))')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(1 2)']], $result->rows);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("SRID value is out of range in 'st_geomfromwkb'");
        (new Instance())->connect()->query('SELECT ST_GEOMFROMWKB(NULL,-1)');
    }

    public function testReadChecksTheDeclaredSubtypeInModernReleases(): void
    {
        $this->expectExceptionCode(3516);
        $this->expectExceptionMessage('WKT value is a geometry of unexpected type LINESTRING in st_pointfromtext.');
        (new Inputs())->read('LINESTRING(1 2,3 4)', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, \SqlSemantics\Contract\GrammarRelease::MySql847, 'st_pointfromtext', 1, false);
    }

    public function testParametersRejectAnUnknownBinaryReferenceBeforeMutatingThePayload(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=1');
        $replies = $session->run('SELECT ST_GEOMFROMWKB((@x:=NULL),1234567)');
        $reply = $replies[count($replies) - 1];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(3548, $reply->getCode());
        $result = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testWarningDistinguishesExplicitLegacyReferenceOperands(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('SELECT ST_GEOMFROMWKB(Point(1,2)), ST_GEOMFROMWKB(Point(1,2),0)');
        self::assertSame([3195, 3196], array_column($session->diagnostics->conditions, 1));
    }

}
