<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PartitionInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'PARTITION BY HASH(a)',
            'PARTITION BY HASH(@@missing)',
            "PARTITION BY HASH(DATE 'bad')",
            'PARTITION BY HASH(CAST(1 AS DATETIME(7)))',
            'PARTITION BY HASH(?)',
            'PARTITION BY HASH(',
            "PARTITION BY HASH('unclosed",
            'PARTITION BROKEN',
            "\nPARTITION BY HASH(a)",
            "/* before\n comment */\nPARTITION BY HASH(a)",
            "PARTITION\nBY HASH(a)",
            "\n\npartition broken",
            '/*!50700 PARTITION BY HASH(a) */',
            "-- x\nPARTITION BY HASH(a)",
            'SELECT 1; PARTITION BY HASH(a)',
            "PARTITION BY HASH(a);\nSELECT 2",
            'CREATE PROCEDURE p() PARTITION BY HASH(a)',
            'CREATE PROCEDURE d.p() PARTITION BY HASH(a)',
            'CREATE PROCEDURE d.p() BEGIN SELECT 1; PARTITION BY HASH(a); END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; PARTITION BY HASH(a); END',
            'CREATE PROCEDURE d.p() PARTITION BY HASH(?)',
            "CREATE PROCEDURE d.p() BEGIN SELECT DATE 'bad'; PARTITION BY HASH(a); END",
            'CREATE PROCEDURE d.p() BEGIN SELECT SQL_CACHE 1; PARTITION BY HASH(a); END',
            'CREATE EVENT e ON SCHEDULE AT NOW() DO PARTITION BY HASH(a)',
            "PREPARE p FROM 'PARTITION BY HASH(a)'",
            "PREPARE p FROM 'PARTITION BY HASH(?)'",
            'CREATE PROCEDURE d.p() PARTITION BROKEN',
            'CREATE PROCEDURE d.p() PARTITION BY HASH(\'unclosed',
            'CREATE PROCEDURE p() PARTITION BROKEN',
            'CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT @@missing; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT DATE \'bad\', CAST(1 AS DATETIME(7)); PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT DATE \'bad\', CAST(1 AT LOCAL AS CHAR); PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT DATE \'bad\', 1 COLLATE missing; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT DATE \'bad\', !1; PARTITION BROKEN; END',
            'CREATE PROCEDURE p() BEGIN SELECT DATE \'bad\'; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR SQLSTATE \'45000\'; DECLARE C CONDITION FOR 1; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE C CURSOR FOR SELECT 2; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x INT; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE CONTINUE HANDLER FOR 1 BEGIN END; DECLARE CONTINUE HANDLER FOR 1 BEGIN END; PARTITION BROKEN; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE c CONDITION FOR 1; BEGIN DECLARE x INT; DECLARE CONTINUE HANDLER FOR c BEGIN END; PARTITION BROKEN; END; END',
            'CREATE DATABASE partition_input',
            'USE partition_input',
            'CREATE TABLE t (a INT NOT NULL) PARTITION BY HASH(a) PARTITIONS 2',
            'INSERT INTO t VALUES (1),(2)',
            'SELECT a FROM t ORDER BY a',
            'ALTER TABLE t PARTITION BY HASH(a) PARTITIONS 3',
            'SELECT a FROM t ORDER BY a',
            'DROP TABLE t',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The standalone and table-partitioning input regressions
     */
    #[DataProvider('providerReleases')]
    public function testStandalonePartitionInputMatchesTheNativeServer(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
