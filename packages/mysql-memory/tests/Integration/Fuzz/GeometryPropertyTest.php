<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class GeometryPropertyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $arguments = ['Point(1,2)', 'LineString(Point(0,0),Point(1,1))', 'Polygon(LineString(Point(0,0),Point(1,0),Point(1,1),Point(0,0)))', 'MultiPoint(Point(1,2),Point(3,4))', 'GeometryCollection()', 'GeometryCollection(GeometryCollection())', 'GeometryCollection(Point(1,2),LineString(Point(0,0),Point(1,1)))', 'NULL', "''", '1'];
        foreach (['DIMENSION', 'GEOMETRYTYPE', 'ISEMPTY', 'NUMGEOMETRIES'] as $property) {
            foreach (['', 'ST_'] as $prefix) {
                foreach ($arguments as $argument) {
                    yield $prefix . $property . $argument => ['SELECT ' . $prefix . $property . '(' . $argument . '); SHOW WARNINGS'];
                }
            }
            foreach (self::providerBinaries() as $name => $bytes) {
                yield $property . ' ' . $name => ['SELECT ST_' . $property . "(X'" . bin2hex($bytes) . "'); SHOW WARNINGS"];
            }
        }
        foreach (['latin1', 'utf8mb4', 'binary'] as $charset) {
            yield 'charset ' . $charset => ['SET NAMES ' . $charset . '; SELECT ST_GEOMETRYTYPE(Point(1,2)), COLLATION(ST_GEOMETRYTYPE(Point(1,2))), COERCIBILITY(ST_GEOMETRYTYPE(Point(1,2)))'];
        }
        yield 'stored properties' => ['CREATE TABLE geometry_properties AS SELECT ST_DIMENSION(Point(1,2)) AS d, ST_GEOMETRYTYPE(Point(1,2)) AS t, ST_ISEMPTY(Point(1,2)) AS e, ST_NUMGEOMETRIES(MultiPoint(Point(1,2))) AS n; SHOW COLUMNS FROM geometry_properties; SELECT * FROM geometry_properties'];
    }

    /**
     * @return iterable<string, string>
     */
    public static function providerBinaries(): iterable
    {
        foreach (range(1, 7) as $type) {
            foreach ([-1, 0, 1] as $count) {
                yield 'header ' . $type . '/' . $count => pack('V', 4326) . "\1" . pack('V', $type) . ($count < 0 ? '' : pack('V', $count));
            }
        }
        $point = new Geometry(1, [[1.0, 2.0]]);
        yield 'unknown srid' => Wkb::write(new Geometry(1, $point->points, [], 1234567));
        yield 'nan' => Wkb::write(new Geometry(1, [[NAN, 1.0]]));
        yield 'short line' => Wkb::write(new Geometry(2, [[1.0, 2.0]]));
        yield 'unclosed ring' => Wkb::write(new Geometry(3, [], [new Geometry(2, [[1.0, 1.0], [2.0, 2.0]])]));
        yield 'empty ring' => Wkb::write(new Geometry(3, [], [new Geometry(2)]));
        yield 'wrong member' => Wkb::write(new Geometry(5, [], [$point]));
        yield 'trailing bytes' => Wkb::write($point) . 'abc';
        yield 'big endian' => pack('V', 0) . "\0" . pack('N', 1) . pack('EE', 1.0, 2.0);
        yield 'nested big endian' => pack('V', 0) . "\1" . pack('VV', 7, 1) . "\0" . pack('N', 1) . pack('EE', 1.0, 2.0);
        yield 'unknown order' => pack('V', 0) . "\xff" . substr(Wkb::write($point), 5);
        yield 'nested empty' => Wkb::write(new Geometry(7, [], [new Geometry(7)]));
    }

    #[DataProvider('providerStatements')]
    public function testGeometryPropertiesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
