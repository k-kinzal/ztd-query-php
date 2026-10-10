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
final class SpatialWriteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerStatements(): iterable
    {
        $version = getenv('MYSQL_VERSION');
        $legacy = is_string($version) && str_starts_with($version, '5.');
        $loose = is_string($version) && str_starts_with($version, '5.6.');
        $types = $legacy ? ['POINT', 'GEOMETRY'] : ['POINT', 'GEOMETRY', 'POINT SRID 0', 'POINT SRID 4326'];
        foreach ($types as $type) {
            foreach (['Point(1,2)', 'LineString(Point(0,0),Point(1,1))', "'text'", 'NULL'] as $value) {
                $error = match (true) {
                    $value === "'text'", !$loose && str_starts_with($type, 'POINT') && str_starts_with($value, 'LineString') => 1416,
                    $type === 'POINT SRID 4326' && $value === 'Point(1,2)' => 3643,
                    default => null,
                };
                yield $type . ' ' . $value => ['CREATE TABLE spatial_write(a ' . $type . '); INSERT INTO spatial_write VALUES(' . $value . '); SELECT HEX(a) FROM spatial_write', $error];
            }
        }
        $line = 'LineString(Point(0,0),Point(1,0),Point(1,1),Point(0,0))';
        foreach (['POINT' => 'Point(1,2)', 'LINESTRING' => $line, 'POLYGON' => 'Polygon(' . $line . ')', 'MULTIPOINT' => 'MultiPoint(Point(1,2))', 'MULTILINESTRING' => 'MultiLineString(' . $line . ')', 'MULTIPOLYGON' => 'MultiPolygon(Polygon(' . $line . '))', 'GEOMETRYCOLLECTION' => 'GeometryCollection(Point(1,2))'] as $type => $value) {
            yield 'stored ' . $type => ['CREATE TABLE spatial_write(a ' . $type . '); INSERT INTO spatial_write VALUES(' . $value . '); SELECT HEX(a) FROM spatial_write', null];
            $other = $type === 'POINT' ? $line : 'Point(1,2)';
            yield 'mismatched ' . $type => ['CREATE TABLE spatial_write(a ' . $type . '); INSERT INTO spatial_write VALUES(' . $other . ')', $loose ? null : 1416];
        }
        foreach (['', 'STRICT_TRANS_TABLES'] as $mode) {
            yield 'ignore ' . $mode => ["SET sql_mode='" . $mode . "'; CREATE TABLE spatial_write(a POINT); INSERT IGNORE INTO spatial_write VALUES('text')", 1416];
        }
        yield 'update subtype' => ['CREATE TABLE spatial_write(a POINT); INSERT INTO spatial_write VALUES(Point(1,2)); UPDATE spatial_write SET a=LineString(Point(0,0),Point(1,1))', $loose ? null : 1416];
        yield 'alter subtype' => ['CREATE TABLE spatial_write(a GEOMETRY); INSERT INTO spatial_write VALUES(LineString(Point(0,0),Point(1,1))); ALTER TABLE spatial_write MODIFY a POINT', $loose ? null : 1416];
        yield 'compatible subtype change' => ['CREATE TABLE spatial_write(a GEOMETRY); INSERT INTO spatial_write VALUES(Point(1,2)); ALTER TABLE spatial_write MODIFY a POINT; SELECT HEX(a) FROM spatial_write', null];
        yield 'subtype inplace' => ['CREATE TABLE spatial_write(a GEOMETRY); ALTER TABLE spatial_write MODIFY a POINT,ALGORITHM=INPLACE', 1846];
        yield 'copy subtype' => ["CREATE TABLE original_spatial(a POINT); CREATE TABLE spatial_write LIKE original_spatial; INSERT INTO spatial_write VALUES('text')", 1416];
        yield 'big endian internal value' => ["CREATE TABLE spatial_write(a POINT); INSERT INTO spatial_write VALUES(x'0000000000000000013ff00000000000004000000000000000')", 1416];
        yield 'unknown reference in unrestricted column' => ["CREATE TABLE spatial_write(a POINT); INSERT INTO spatial_write VALUES(x'7b0000000101000000000000000000f03f0000000000000040'); SELECT HEX(a) FROM spatial_write", null];
        if (!$legacy) {
            yield 'add restriction' => ['CREATE TABLE spatial_write(a POINT); INSERT INTO spatial_write VALUES(Point(1,2)); ALTER TABLE spatial_write MODIFY a POINT SRID 0; SELECT HEX(a) FROM spatial_write', null];
            yield 'remove restriction' => ['CREATE TABLE spatial_write(a POINT SRID 0); INSERT INTO spatial_write VALUES(Point(1,2)); ALTER TABLE spatial_write MODIFY a POINT; SELECT HEX(a) FROM spatial_write', null];
            yield 'SRID inplace' => ['CREATE TABLE spatial_write(a POINT); ALTER TABLE spatial_write MODIFY a POINT SRID 0,ALGORITHM=INPLACE', 1845];
            yield 'SRID lock none' => ['CREATE TABLE spatial_write(a POINT); ALTER TABLE spatial_write MODIFY a POINT SRID 0,LOCK=NONE', 1846];
            yield 'ignore SRID' => ['CREATE TABLE spatial_write(a POINT SRID 4326); INSERT IGNORE INTO spatial_write VALUES(Point(1,2))', 3643];
            yield 'matching SRID' => ["CREATE TABLE spatial_write(a POINT SRID 4326); INSERT INTO spatial_write VALUES(x'e61000000101000000000000000000f03f0000000000000040'); SELECT HEX(a) FROM spatial_write", null];
            yield 'alter SRID' => ['CREATE TABLE spatial_write(a POINT); INSERT INTO spatial_write VALUES(Point(1,2)); ALTER TABLE spatial_write MODIFY a POINT SRID 4326', 3643];
            yield 'update SRID' => ['CREATE TABLE spatial_write(a POINT SRID 4326); INSERT INTO spatial_write VALUES(NULL); UPDATE spatial_write SET a=Point(1,2)', 3643];
            yield 'generated SRID' => ['CREATE TABLE spatial_write(a INT,b POINT AS (Point(a,a)) STORED SRID 4326); INSERT INTO spatial_write(a) VALUES(1)', 3643];
        }
    }

    #[DataProvider('providerStatements')]
    public function testSpatialWritesMatchTheServer(string $sql, ?int $error): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        $actual = $target->run($target->memory, 'root', '', $sql)['error'] ?? [null];
        self::assertIsArray($actual);
        self::assertSame($error, $actual[0]);
    }
}
