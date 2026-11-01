<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Properties;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Properties::class)]
#[Small]
final class PropertiesTest extends TestCase
{
    public function testRoutinesRegisterAllCurrentAndLegacyNames(): void
    {
        self::assertCount(8, (new Properties())->routines());
    }

    public function testValueReadsCollectionDimensionsAndCountsIndependently(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ST_DIMENSION(GeometryCollection(GeometryCollection())), ST_ISEMPTY(GeometryCollection(GeometryCollection())), ST_NUMGEOMETRIES(GeometryCollection(GeometryCollection())), ST_GEOMETRYTYPE(Point(1,2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1', '1', 'POINT']], $result->rows);
    }

    public function testGeometryPreservesBothConditionsOfARefusedByteOrder(): void
    {
        try {
            (new Properties())->geometry(pack('V', 0) . "\0" . pack('N', 1) . pack('EE', 1.0, 2.0), 'st_srid');
            self::fail('A structural property must refuse big-endian input.');
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertSame(3055, $error->getCode());
            self::assertSame([[3037, 'Invalid GIS data provided to function st_srid.']], $error->following);
        }
    }

    public function testReadableChecksStructureWithoutRequiringFiniteCoordinatesOrClosedRings(): void
    {
        $properties = new Properties();
        self::assertTrue($properties->readable(new Geometry(1, [[NAN, 1.0]])));
        self::assertTrue($properties->readable(new Geometry(2, [[1.0, 2.0]])));
        self::assertFalse($properties->readable(new Geometry(3, [], [new Geometry(2)])));
        self::assertFalse($properties->readable(new Geometry(5, [], [new Geometry(1, [[1.0, 2.0]])])));
    }

    public function testDimensionIgnoresEmptyMembersAndFindsTheLargestDimension(): void
    {
        self::assertSame(2, (new Properties())->dimension(new Geometry(7, [], [new Geometry(7), new Geometry(1), new Geometry(3)])));
        self::assertNull((new Properties())->dimension(new Geometry(7)));
    }

    public function testLegacyReadsHeaderCountsWithoutRequiringTheirPayload(): void
    {
        $bytes = pack('V', 4326) . "\xff" . pack('VV', 7, 1);
        self::assertSame('GEOMETRYCOLLECTION', (new Properties())->legacy($bytes, 'GEOMETRYTYPE'));
        self::assertSame(1, (new Properties())->legacy($bytes, 'NUMGEOMETRIES'));
        self::assertSame(0, (new Properties())->legacy($bytes, 'ISEMPTY'));
        self::assertNull((new Properties())->legacy($bytes, 'DIMENSION'));
    }
}
