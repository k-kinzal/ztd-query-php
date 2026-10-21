<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\GenerationAllocation;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedGroupedDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int, bool}>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $statements = [['CREATE DATABASE d', 0, false], ['USE d', 0, false], ['SET @run=0', 0, false]];
            $expressions = ['SUM(a)', 'ABS(SUM(a))', 'GROUP_CONCAT(a)', 'JSON_OBJECTAGG(a,1)', 'DEFAULT(a)'];
            if ($release !== '5.7.44') {
                array_push($expressions, 'ROW_NUMBER() OVER ()', 'SUM(a) OVER ()', 'COUNT(*) OVER ()');
            }
            foreach ($expressions as $expression) {
                $create = 'CREATE TABLE t(a INT,g INT AS (' . $expression . '))';
                array_push($statements, ['DROP TABLE IF EXISTS t', 0, false], [$create, 0, false], ['SHOW CREATE TABLE t', 0, false], ['DROP TABLE IF EXISTS t', 0, false], [$create, 0, true], ['DROP TABLE IF EXISTS t', 0, false], ['CREATE TABLE t(a INT)', 0, false], ['INSERT INTO t VALUES (7)', 0, false], [str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $create), 0, true], ['ALTER TABLE t ADD g INT AS (' . $expression . ')', 0, false]);
                $program = 'CREATE PROCEDURE p() BEGIN IF @run THEN CREATE TABLE IF NOT EXISTS t(a INT,g INT AS (' . $expression . ')); END IF; END';
                array_push($statements, [$program, 0, false], ['CALL p()', 0, false], ['SET @run=1', 0, false], ['CALL p()', 0, false], ['DROP TABLE t', 0, false], ['CALL p()', 0, false], ['SET @run=0', 0, false], ['CALL p()', 0, false], ['DROP PROCEDURE p', 0, false]);
            }
            yield $release => [$release, $statements];
            $prepared = ['CREATE DATABASE d', 'USE d', "PREPARE s FROM 'CREATE TABLE IF NOT EXISTS t(a INT,g INT AS (SUM(a)))'", 'EXECUTE s', "SHOW SESSION STATUS LIKE 'Com_stmt_%'", 'CREATE TABLE t(a INT)', 'EXECUTE s', "SHOW SESSION STATUS LIKE 'Com_stmt_%'", 'DROP TABLE t', 'EXECUTE s', "SHOW SESSION STATUS LIKE 'Com_stmt_%'", 'SELECT 1'];
            yield $release . ':prepared' => [$release, array_map(static fn (string $sql): array => [$sql, 0, false], $prepared)];
        }
    }

    /**
     * @param list<array{string, int, bool}> $statements
     */
    #[DataProvider('providerStatements')]
    public function testGeneratedGroupsKeepTheirConditionalPreparationRole(string $release, array $statements): void
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testBinaryPreparationRetainsGroupedOperandsAcrossDestinationChanges(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $observe = static function (mysqli $database): array {
                    $database->query('DROP DATABASE IF EXISTS d');
                    $database->query('CREATE DATABASE d');
                    $database->select_db('d');
                    $statement = $database->prepare('CREATE TABLE IF NOT EXISTS t(a INT,g INT AS (SUM(a)))');
                    self::assertInstanceOf(mysqli_stmt::class, $statement);
                    $execute = static function () use ($statement, $database): array {
                        try {
                            $statement->execute();
                            $result = ['affected' => $statement->affected_rows];
                        } catch (mysqli_sql_exception $error) {
                            $result = ['error' => [$error->getCode(), $error->getSqlState(), $error->getMessage()]];
                        }
                        return [$result, (new Native())->conditions($database), (new Native())->observe($database, "SHOW SESSION STATUS LIKE 'Com_stmt_%'")];
                    };
                    $before = $execute();
                    $database->query('CREATE TABLE t(a INT)');
                    $present = $execute();
                    $database->query('DROP TABLE t');
                    $removed = $execute();
                    $statement->close();
                    $database->close();
                    return [$before, $present, $removed];
                };
                $expected = $observe($native);
                $actual = $observe(new mysqli($server->host, 'root', '', port: $server->port));
                $contract = new GenerationAllocation();
                self::assertSame($contract->comparable($expected, $release), $contract->comparable($actual, $release), serialize(['contract' => GenerationAllocation::CONTRACT, 'release' => $release, 'native' => $expected, 'memory' => $actual]));
                $expected = $observe(new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port));
                $actual = $observe(new mysqli($server->host, 'root', '', port: $server->port));
                self::assertSame($contract->comparable($expected, $release), $contract->comparable($actual, $release), serialize(['contract' => GenerationAllocation::CONTRACT, 'release' => $release, 'native' => $expected, 'memory' => $actual]));
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

}
