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
final class SpatialIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerStatements(): iterable
    {
        $version = getenv('MYSQL_VERSION');
        $legacy = is_string($version) && str_starts_with($version, '5.');
        $rtreeError = is_string($version) && str_starts_with($version, '5.7.') ? 1070 : 1210;
        $listing = '; SHOW WARNINGS; SHOW CREATE TABLE spatial_probe; SHOW INDEX FROM spatial_probe';
        foreach (['InnoDB', 'MyISAM', 'MEMORY'] as $engine) {
            foreach (['INT', 'POINT', 'POINT NOT NULL', 'GEOMETRY NOT NULL'] as $type) {
                foreach (['KEY', 'UNIQUE KEY'] as $kind) {
                    $error = $engine === 'MEMORY' && $type !== 'INT' ? 1178 : ($legacy ? $rtreeError : ($type === 'INT' ? 1687 : ($kind === 'UNIQUE KEY' ? 3728 : ($type === 'POINT' ? 1252 : null))));
                    yield $engine . ' ' . $type . ' ' . $kind => ['CREATE TABLE spatial_probe(a ' . $type . ',' . $kind . ' k(a) USING RTREE) ENGINE=' . $engine . $listing, $error];
                }
            }
            yield $engine . ' geometric fulltext' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,FULLTEXT KEY k(a)) ENGINE=' . $engine, $engine === 'MEMORY' ? 1178 : 1283];
            yield $engine . ' replaced RTREE' => ['CREATE TABLE spatial_probe(a INT,KEY k(a) USING RTREE USING BTREE) ENGINE=' . $engine . $listing, null];
        }
        yield 'existing unsupported HASH' => ['CREATE TEMPORARY TABLE spatial_probe(a INT); CREATE TEMPORARY TABLE spatial_probe(a INT,KEY k(a) USING HASH)', 1050];
        yield 'existing conditional HASH' => ['CREATE TEMPORARY TABLE spatial_probe(a INT); CREATE TEMPORARY TABLE IF NOT EXISTS spatial_probe(a INT,KEY k(a) USING HASH); SHOW WARNINGS', null];
        yield 'MyISAM explicit spatial' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,SPATIAL KEY k(a)) ENGINE=MyISAM' . $listing, null];
        yield 'MEMORY explicit spatial' => ['CREATE TABLE spatial_probe(a INT,SPATIAL KEY k(a)) ENGINE=MEMORY', 1464];
        if (!$legacy) {
            yield 'implicit spatial' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,KEY k(a))' . $listing, null];
            yield 'explicit spatial' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,SPATIAL KEY k(a))' . $listing, null];
            yield 'unsupported BTREE' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,KEY k(a) USING BTREE)', 3729];
            yield 'unsupported HASH' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,KEY k(a) USING HASH)', 3729];
            yield 'unique unsupported HASH' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,UNIQUE KEY k(a) USING HASH)', 3729];
            yield 'composite unsupported HASH' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,b INT,KEY k(a,b) USING HASH)', 1070];
            yield 'unknown HASH column' => ['CREATE TABLE spatial_probe(a INT,KEY k(missing) USING HASH)', 1072];
            yield 'prefix' => ['CREATE TABLE spatial_probe(a POINT NOT NULL,KEY k(a(10)))', 1089];
        }
    }

    #[DataProvider('providerStatements')]
    public function testSpatialIndexDefinitionsAndDiagnosticsMatchTheServer(string $sql, ?int $error): void
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
