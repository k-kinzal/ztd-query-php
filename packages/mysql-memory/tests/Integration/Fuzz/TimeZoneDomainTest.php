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
final class TimeZoneDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d'];
        $queries = [];
        foreach (['10', 'Etc/UTC', 'utc', '-00:00', '+00:01', '+00:00'] as $zone) {
            foreach (['NULL', "TIMESTAMP'2024-01-01 00:00:00'", '1', 'missing', 'a', 'b', '(SELECT a FROM u)', 'ROW(1,2)', 'NULL REGEXP NULL', 'a REGEXP b', 'a=b', 'ABS(a)', 'ABS(missing)', '(SELECT 1)', 'MIN(a)', 'MAX(a)', '?', '(?)', 'TIMESTAMPADD(DAY,0,a)', 'TIMESTAMPADD(MINUTE,?,a)', 'TIMESTAMPDIFF(DAY,a,?)'] as $operand) {
                $queries[] = 'SELECT CAST(' . $operand . " AT TIME ZONE '" . $zone . "' AS DATETIME) AS v FROM t";
            }
        }
        foreach ([[], ['CREATE TABLE t(a TIMESTAMP NULL,b DATETIME)', 'CREATE TABLE u(a TIMESTAMP NULL)'], ["INSERT INTO t VALUES('2024-01-01','2024-01-01')", "INSERT INTO u VALUES('2024-01-01')"]] as $setup) {
            array_push($statements, ...$setup);
            foreach ($queries as $query) {
                array_push($statements, $query, "PREPARE p FROM '" . str_replace("'", "''", $query) . "'", 'EXECUTE p', 'DEALLOCATE PREPARE p');
            }
        }
        foreach (['NULL', 'a', 'b'] as $index => $operand) {
            $name = 'run_zone' . $index;
            array_push($statements, 'CREATE PROCEDURE ' . $name . '(flag INT) BEGIN IF flag THEN SELECT CAST(' . $operand . " AT TIME ZONE '10' AS DATETIME) FROM t; END IF; END", 'CALL ' . $name . '(0)', 'CALL ' . $name . '(1)', 'CALL ' . $name . '(0)');
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Zone conditions across dependency, row and invocation states
     */
    #[DataProvider('providerInputs')]
    public function testZoneConstraintsPreserveOperandPreparationAndStoredInvocation(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield '8.0.44' => ['8.0.44'];
        yield '8.4.7' => ['8.4.7'];
        yield '9.1.0' => ['9.1.0'];
    }

    #[DataProvider('providerReleases')]
    public function testAnInstalledUtcAliasStillCannotBeUsedByTheCast(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query("INSERT INTO mysql.time_zone (Use_leap_seconds) VALUES ('N')");
            $native->query('SET @zone_id=LAST_INSERT_ID()');
            $native->query("INSERT INTO mysql.time_zone_name (Name,Time_zone_id) VALUES ('10',@zone_id)");
            $native->query("INSERT INTO mysql.time_zone_transition_type (Time_zone_id,Transition_type_id,Offset,Is_DST,Abbreviation) VALUES (@zone_id,0,0,0,'UTC')");
            $conversion = (new Native())->observe($native, "SELECT CONVERT_TZ('2024-01-01 00:00:00','10','+00:00')");
            self::assertSame([['2024-01-01 00:00:00']], $conversion['results'][0]['rows']);
            $cast = (new Native())->observe($native, "SELECT CAST(NULL AT TIME ZONE '10' AS DATETIME)");
            self::assertSame([1298, 'HY000', "Unknown or incorrect time zone: '10'"], $cast['error'] ?? null);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
