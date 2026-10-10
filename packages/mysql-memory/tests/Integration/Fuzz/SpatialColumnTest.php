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
final class SpatialColumnTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerStatements(): iterable
    {
        $version = getenv('MYSQL_VERSION');
        if (is_string($version) && str_starts_with($version, '5.')) {
            yield 'unsupported SRID restriction' => ['CREATE TABLE spatial_column(a POINT SRID 4326)', 1064];
            yield 'unsupported SRID on generated column' => ['CREATE TABLE spatial_column(a POINT AS (NULL) STORED SRID 4326)', 1064];

            return;
        }
        $listing = '; SHOW WARNINGS; SHOW CREATE TABLE spatial_column; SELECT COLUMN_NAME,SRS_ID FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'spatial_column\' ORDER BY ORDINAL_POSITION';
        foreach (['POINT SRID 0', 'POINT NOT NULL SRID 4326', "POINT NOT NULL SRID 4326 COMMENT 'x'", 'POINT SRID 4326 SRID 0', 'POINT SRID 99999999 SRID 0x10E6', 'POINT SRID 0004326', 'POINT AS (NULL) STORED SRID 4326', 'POINT NOT NULL SRID 4326,SPATIAL KEY k(a)'] as $definition) {
            yield $definition => ['CREATE TABLE spatial_column(a ' . $definition . ')' . $listing, null];
        }
        foreach (['POINT SRID 99999999', 'POINT SRID 4294967295', 'POINT SRID 99999999,KEY k(missing)', 'POINT NOT NULL SRID 99999999,KEY k(a) USING HASH'] as $definition) {
            yield $definition => ['CREATE TABLE spatial_column(a ' . $definition . ')', 3548];
        }
        foreach (['POINT SRID 4294967296', 'POINT SRID 0x100000000', 'POINT SRID 18446744073709551615', 'POINT SRID 4294967296 SRID 0', 'INT SRID 4294967296'] as $definition) {
            yield $definition => ['CREATE TABLE spatial_column(a ' . $definition . ')', 1690];
        }
        foreach (['', ' ORDER BY COLUMN_NAME', ' ORDER BY COLUMN_NAME LIMIT 0', ' ORDER BY ORDINAL_POSITION LIMIT 1'] as $order) {
            yield 'metadata' . $order => ['CREATE TABLE spatial_column(a POINT SRID 4326); SELECT COLUMN_NAME,SRS_ID FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'spatial_column\'' . $order, null];
        }
        yield 'non-geometric' => ['CREATE TABLE spatial_column(a INT SRID 4326)', 1221];
        yield 'unknown non-geometric' => ['CREATE TABLE spatial_column(a INT SRID 99999999)', 1221];
        yield 'fractional' => ['CREATE TABLE spatial_column(a POINT SRID 1.0)', 1064];
        yield 'exponential' => ['CREATE TABLE spatial_column(a POINT SRID 1e0)', 1064];
        yield 'alter unknown' => ['CREATE TABLE spatial_column(a INT); ALTER TABLE spatial_column ADD b POINT SRID 99999999', 3548];
        yield 'alter range' => ['CREATE TABLE spatial_column(a INT); ALTER TABLE spatial_column ADD b POINT SRID 4294967296', 1690];
        yield 'alter known' => ['CREATE TABLE spatial_column(a POINT); ALTER TABLE spatial_column MODIFY a POINT SRID 4326' . $listing, null];
        yield 'existing' => ['CREATE TABLE spatial_column(a INT); CREATE TABLE spatial_column(a POINT SRID 99999999)', 1050];
        yield 'conditional existing' => ['CREATE TABLE spatial_column(a INT); CREATE TABLE IF NOT EXISTS spatial_column(a POINT SRID 99999999); SHOW WARNINGS', null];
        yield 'copy' => ['CREATE TABLE original_spatial(a POINT SRID 4326); CREATE TABLE spatial_column LIKE original_spatial' . $listing, null];
    }

    #[DataProvider('providerStatements')]
    public function testSpatialColumnRestrictionsMatchTheServer(string $sql, ?int $error): void
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
