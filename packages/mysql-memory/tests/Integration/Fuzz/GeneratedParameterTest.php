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
final class GeneratedParameterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [['CREATE TABLE t(g INT AS (?))', 1], ["PREPARE s FROM 'CREATE TABLE t(g INT AS (?))'", 0], ['CREATE DATABASE d', 0], ['USE d', 0]];
            foreach (['?', '(?)', '?+1', '1-?', 'ABS(?)', 'COALESCE(?,1)', 'CASE WHEN 1 THEN ? ELSE 0 END', '?+a', '@x+?', '(SELECT ?)'] as $expression) {
                foreach (['NULL', '7', '-7', '1.5', "'7'"] as $value) {
                    $sql = 'CREATE TABLE t(a INT, g INT AS (' . $expression . '))';
                    array_push($statements, ['DROP TABLE IF EXISTS t', 0], ['SET @x=' . $value, 0], ["PREPARE s FROM '" . str_replace("'", "''", $sql) . "'", 0], ['EXECUTE s USING @x', 0], ['DEALLOCATE PREPARE s', 0], ['SHOW CREATE TABLE t', 0], ['INSERT INTO t(a) VALUES(2)', 0], ['SELECT * FROM t', 0]);
                }
            }
            array_push($statements, ['DROP TABLE IF EXISTS t', 0], ['CREATE TABLE t(a INT)', 0]);
            foreach (['CREATE TABLE IF NOT EXISTS t(g INT AS (?))', 'CREATE TABLE missing.t(g INT AS (?))', 'ALTER TABLE t ADD g INT AS (?)', 'ALTER TABLE absent ADD g INT AS (?)', 'ALTER TABLE t ADD g INT AS (?+a)'] as $sql) {
                $statements[] = [$sql, 1];
            }
            array_push($statements, ['SHOW CREATE TABLE t', 0], ['INSERT INTO t(a) VALUES(2)', 0], ['SELECT * FROM t', 0]);
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<array{string, int}> $statements
     */
    #[DataProvider('providerStatements')]
    public function testGenerationParametersPreservePreparationAndStoredValues(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (array $input): string => serialize((new Native())->observe($native, ...$input)), $statements);
                $actual = array_map(static fn (array $input): string => serialize((new Native())->observe($memory, ...$input)), $statements);
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
