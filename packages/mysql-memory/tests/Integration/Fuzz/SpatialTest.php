<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class SpatialTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerExpressions(): iterable
    {
        $line = 'LineString(Point(0,0),Point(1,0),Point(1,1),Point(0,0))';
        $values = ['Point(1,2)', $line, "Polygon($line)", 'MultiPoint(Point(1,2))', "MultiLineString($line)", "MultiPolygon(Polygon($line))", 'GeometryCollection(Point(1,2))', 'GeometryCollection()'];
        foreach ($values as $index => $value) {
            yield "constructor $index" => ['SELECT ' . $value];
            foreach (['POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION'] as $target) {
                yield "cast $index to $target" => ["SELECT CAST($value AS $target)"];
            }
        }
        foreach ([
            "Point('x','y')", 'Point(NULL,1)', 'LineString(Point(1,2))', 'LineString(NULL)', 'LineString(USER())', 'LineString(CAST(NULL AS POINT))',
            'MultiPoint(LineString(Point(0,0),Point(1,1)))', 'Polygon(LineString(Point(0,0),Point(1,1)))', 'COERCIBILITY(Point(1,2))',
            'CAST(NULL AS POINT)', "CAST('x' AS POINT)", 'CAST(1 AS GEOMETRYCOLLECTION)',
            'CAST(LineString(Point(0,0),Point(1,1),Point(1,0),Point(0,0)) AS POLYGON)',
            'CAST(MultiPoint(Point(0,0),Point(1,1)) AS LINESTRING)',
            "1 MEMBER OF (CAST('x' AS POINT))", "1 MEMBER OF (POINT('x','y'))", '1 MEMBER OF (GeometryCollection())',
        ] as $index => $expression) {
            yield "validation $index" => ['SELECT ' . $expression];
        }
        foreach ([
            'SELECT GeomCollection()',
            "SELECT Point(NULL,'x'), Point('x',NULL)",
            "SELECT CAST(UNHEX('000000000101000000000000000000f03f0000000000000040') AS POINT)",
            "SELECT CAST(UNHEX('7b0000000101000000000000000000f03f0000000000000040') AS POINT)",
            "SELECT CAST(UNHEX('e61000000101000000000000000000f03f0000000000000040') AS MULTIPOINT)",
        ] as $index => $sql) {
            yield "binary and alias $index" => [$sql];
        }
        foreach (['POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION'] as $target) {
            yield "member cast $target" => ["KILL USER() MEMBER OF (CAST(USER() AS $target))"];
        }
        foreach (['LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON'] as $function) {
            yield "member constructor $function" => ["KILL USER() MEMBER OF ($function(USER()))"];
        }
    }

    #[DataProvider('providerExpressions')]
    public function testSpatialExpressionsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
