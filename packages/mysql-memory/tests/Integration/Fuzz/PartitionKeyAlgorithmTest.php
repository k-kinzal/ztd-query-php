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
final class PartitionKeyAlgorithmTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [];
            foreach ([false, true] as $database) {
                if ($database) {
                    array_push($statements, ['CREATE DATABASE d', 0], ['USE d', 0]);
                }
                if ($release === '5.7.44') {
                    $statements[] = ["CREATE TABLE IF NOT EXISTS `PRIMARY` (A DECIMAL AS (FALSE), CHECK (?), CHECK (GROUP_CONCAT(TRUE, ? SEPARATOR '3'))) PARTITION BY KEY ALGORITHM = x'' (t, `0`, `1`) PARTITIONS 12345 SUBPARTITION BY LINEAR HASH (NULL) SUBPARTITIONS 10", 2];
                }
                foreach (['1', '2', '01', '0x01', "x'02'", '0', '3', "x''", '4294967297', '1.0'] as $algorithm) {
                    $sql = 'CREATE TABLE t(a INT) PARTITION BY KEY ALGORITHM = ' . $algorithm . ' (a)';
                    array_push($statements, [$sql, 0], ['SHOW CREATE TABLE t', 0], ['DROP TABLE IF EXISTS t', 0], [$sql, 1], ['DROP TABLE IF EXISTS t', 0]);
                    foreach ([' BROKEN', '', ' PARTITIONS 0'] as $tail) {
                        $statements[] = ['CREATE TABLE t(a INT) PARTITION BY KEY ALGORITHM = ' . $algorithm . ' (missing)' . $tail, 0];
                    }
                    $statements[] = ['CREATE PROCEDURE p() BEGIN IF 0 THEN ' . $sql . '; END IF; END', 0];
                    $statements[] = ['DROP PROCEDURE IF EXISTS p', 0];
                    $statements[] = ['CREATE TABLE t(a INT) PARTITION BY RANGE(a) SUBPARTITION BY KEY ALGORITHM = ' . $algorithm . ' (a) (PARTITION p0 VALUES LESS THAN MAXVALUE)', 0];
                    $statements[] = ['DROP TABLE IF EXISTS t', 0];
                }
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<array{string, int}> $statements
     */
    #[DataProvider('providerStatements')]
    public function testAlgorithmsPreservePreparationAndDefinitionBehavior(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (array $input): string => serialize((new Native())->observe($native, $input[0], $input[1] === 2 ? 2 : 0, prepared: $input[1] > 0)), $statements);
                $actual = array_map(static fn (array $input): string => serialize((new Native())->observe($memory, $input[0], $input[1] === 2 ? 2 : 0, prepared: $input[1] > 0)), $statements);
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
