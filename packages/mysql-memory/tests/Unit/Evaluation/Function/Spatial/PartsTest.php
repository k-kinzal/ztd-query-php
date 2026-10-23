<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Parts;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Parts::class)]
#[Small]
final class PartsTest extends TestCase
{
    public function testRoutinesRegisterTheComponentAndCountFunctions(): void
    {
        self::assertCount(19, (new Parts())->routines());
    }

    public function testValuePreservesTheParentReferenceWhenExtractingAPoint(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_ASTEXT(ST_POINTN(ST_GEOMFROMTEXT('LINESTRING(1 2,3 4)',4326),2)), ST_SRID(ST_STARTPOINT(ST_GEOMFROMTEXT('LINESTRING(1 2,3 4)',4326)))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(3 4)', '4326']], $result->rows);
    }

    public function testValueConvertsTheIndexEvenForANullGeometry(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ST_POINTN(NULL,'1.9')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '1.9'"]], $session->diagnostics->conditions);
    }

    public function testReadPreservesLegacyTrailingBytesAndRejectsMalformedModernInput(): void
    {
        $line = new Geometry(2, [[1.0, 2.0], [3.0, 4.0]], [], 4326);
        $bytes = Wkb::write($line) . 'tail';
        self::assertEquals($line, (new Parts())->read($bytes, GrammarRelease::MySql5651, 'POINTN'));
        $this->expectExceptionCode(3037);
        $this->expectExceptionMessage('Invalid GIS data provided to function st_pointn.');
        (new Parts())->read($bytes, GrammarRelease::MySql847, 'POINTN');
    }

    public function testSelectKeepsNestedMembersAndOneBasedIndexes(): void
    {
        $nested = new Geometry(7, [], [new Geometry(1, [[1.0, 2.0]])]);
        $geometry = new Geometry(7, [], [$nested, new Geometry(7)]);
        self::assertSame($nested, (new Parts())->select($geometry, 'GEOMETRYN', 1));
        self::assertNull((new Parts())->select($geometry, 'GEOMETRYN', 0));
        self::assertNull((new Parts())->select($geometry, 'GEOMETRYN', 3));
        self::assertNull((new Parts())->select($geometry, 'POINTN', 1));
    }

    public function testClosedRequiresEachLineToHaveMatchingEndpoints(): void
    {
        $closed = new Geometry(2, [[1.0, 2.0], [3.0, 4.0], [1.0, 2.0]]);
        $open = new Geometry(2, [[1.0, 2.0], [3.0, 4.0]]);
        self::assertSame(1, (new Parts())->closed(new Geometry(5, [], [$closed, $closed])));
        self::assertSame(0, (new Parts())->closed(new Geometry(5, [], [$closed, $open])));
        self::assertNull((new Parts())->closed(new Geometry(7, [], [$closed])));
    }

    public function testLegacyCountReadsTheHeaderWithoutAllocatingTheClaimedPoints(): void
    {
        self::assertSame(4294967295, (new Parts())->legacyCount(pack('V', 0) . "\1" . pack('VV', 2, 4294967295), 'NUMPOINTS'));
        self::assertSame(0, (new Parts())->legacyCount(pack('V', 0) . "\1" . pack('VV', 2, 0), 'NUMPOINTS'));
        self::assertNull((new Parts())->legacyCount(pack('V', 0) . "\1" . pack('VV', 3, 0), 'NUMINTERIORRINGS'));
    }

    public function testPointDoesNotTreatAPointValueAsALine(): void
    {
        self::assertNull((new Parts())->point(new Geometry(1, [[1.0, 2.0]]), 1));
        self::assertEquals(new Geometry(1, [[3.0, 4.0]]), (new Parts())->point(new Geometry(2, [[1.0, 2.0], [3.0, 4.0]]), 2));
    }

    public function testIndexTruncatesAssignmentTextWithoutHidingTheAssignmentNotice(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ST_ASTEXT(ST_POINTN(LineString(Point(1,2),Point(3,4)),(@n:='1.9')))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(1 2)']], $result->rows);
        self::assertSame([1287], array_column($session->diagnostics->conditions, 1));
        $session->query('SELECT CAST(@n AS SIGNED)');
        self::assertSame([1292], array_column($session->diagnostics->conditions, 1));
    }
}
