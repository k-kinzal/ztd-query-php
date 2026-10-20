<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialKeyArityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $original = "CREATE SPATIAL INDEX `2024-01-01` ON a (('00000' SOUNDS LIKE TRUE), a)";
        $statements = [$original, 'CREATE DATABASE d', 'USE d', $original, 'CREATE TABLE a(fixture_value INT)', 'INSERT INTO a VALUES(7)', $original];
        foreach (['InnoDB', 'MyISAM', 'MEMORY'] as $engine) {
            array_push($statements, 'DROP TABLE IF EXISTS t', 'CREATE TABLE t(a INT,b INT) ENGINE=' . $engine, 'INSERT INTO t VALUES(7,8)');
            foreach (['a,b', 'a,a', 'missing,a', '(1+1),a', '(ROW(1,2)),a', '(RAND()),a', '(missing+1),a', "(CAST(FALSE AT TIME ZONE 't' AS DATETIME)),a", "('00000' SOUNDS LIKE TRUE),a", 'a,(RAND())'] as $parts) {
                array_push($statements, 'CREATE SPATIAL INDEX k ON t(' . $parts . ')', 'ALTER TABLE t ADD SPATIAL k(' . $parts . ')', 'CREATE TABLE fresh(a INT,b INT,SPATIAL k(' . $parts . ')) ENGINE=' . $engine, 'CREATE TABLE IF NOT EXISTS t(a INT,b INT,SPATIAL k(' . $parts . ')) ENGINE=' . $engine, 'DROP TABLE IF EXISTS fresh');
            }
        }
        foreach (['KEY ordinary(missing),SPATIAL k(a,b)', 'SPATIAL k(a,b),KEY ordinary(missing)', 'KEY `PRIMARY`(a),SPATIAL k(a,b)', 'SPATIAL k(a,b),KEY `PRIMARY`(a)', 'SPATIAL k(a,b),KEY ordinary(a(4))'] as $parts) {
            array_push($statements, 'CREATE TABLE fresh(a INT,b INT,' . $parts . ') ENGINE=MyISAM', 'ALTER TABLE t ADD ' . str_replace('),', '), ADD ', $parts));
        }
        foreach (['CREATE SPATIAL INDEX k ON t(a,b)', 'ALTER TABLE t ADD SPATIAL k(a,b)', 'CREATE TABLE IF NOT EXISTS t(a INT,b INT,SPATIAL k(a,b)) ENGINE=MyISAM', 'CREATE TABLE IF NOT EXISTS t(SPATIAL k(a,b)) ENGINE=MyISAM AS SELECT 1 AS a, 2 AS b'] as $form => $body) {
            $procedure = 'p_' . $form;
            array_push($statements, 'DROP TABLE t', 'CREATE PROCEDURE ' . $procedure . '() BEGIN IF @run THEN ' . $body . '; END IF; END', 'SET @run=0', 'CALL ' . $procedure . '()', 'SET @run=1', 'CALL ' . $procedure . '()', 'CREATE TABLE t(a INT,b INT) ENGINE=MyISAM', 'INSERT INTO t VALUES(7,8)', 'CALL ' . $procedure . '()', 'CALL ' . $procedure . '()', 'ALTER TABLE t ENGINE=MEMORY', 'CALL ' . $procedure . '()', 'SET @run=0', 'CALL ' . $procedure . '()');
        }
        array_push($statements, 'CREATE TABLE fresh(a INT,a INT,SPATIAL k(a,b))', 'ALTER TABLE t ADD a INT, ADD SPATIAL k(a,b)', 'ALTER TABLE t DROP COLUMN absent, ADD SPATIAL k(a,b)', 'CREATE SPATIAL INDEX k ON absent(a,b)', "SHOW SESSION STATUS WHERE Variable_name IN ('Com_create_index','Com_alter_table','Com_create_table')");
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Skipped and reached instructions with missing and populated targets
     */
    #[DataProvider('providerReleases')]
    public function testSpatialKeyArityPreservesDeclarationAndInvocationPriority(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && (str_starts_with($sql, 'CREATE SPATIAL INDEX') || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && (str_starts_with($sql, 'CREATE SPATIAL INDEX') || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
