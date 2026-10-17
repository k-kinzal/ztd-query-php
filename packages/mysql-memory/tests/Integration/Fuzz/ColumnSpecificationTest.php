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
final class ColumnSpecificationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['BIT NULL COLLATE unknown', 'INT COLLATE unknown AS (1)', 'INT AS (1) DEFAULT 0', 'SERIAL AS (1)', 'INT AS (1) ON UPDATE CURRENT_TIMESTAMP', 'INT AS (1) AUTO_INCREMENT', 'INT AS (1) SERIAL DEFAULT VALUE', 'INT AS (1) COLUMN_FORMAT FIXED', 'INT AS (1) STORAGE MEMORY'] as $definition) {
            $statements[] = 'ALTER TABLE t MODIFY a ' . $definition;
            $statements[] = 'CREATE TABLE t(a ' . $definition . ')';
            $statements[] = 'DROP TABLE IF EXISTS t';
            $statements[] = 'CREATE PROCEDURE p() BEGIN IF 0 THEN ALTER TABLE t MODIFY a ' . $definition . '; END IF; END';
            $statements[] = 'DROP PROCEDURE IF EXISTS p';
        }
        foreach (['BIT DEFAULT CURRENT_TIMESTAMP DEFAULT 0', 'DATETIME(3) DEFAULT CURRENT_TIMESTAMP(2) DEFAULT CURRENT_TIMESTAMP(3)', 'DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(2) ON UPDATE CURRENT_TIMESTAMP(3)'] as $definition) {
            $statements[] = 'CREATE TABLE t(a ' . $definition . ')';
            $statements[] = 'DROP TABLE IF EXISTS t';
        }
        $statements[] = 'CREATE TABLE t(a BIT NULL COLLATE unknown, BOGUS)';
        $statements[] = 'CREATE PROCEDURE p() BEGIN ALTER TABLE t MODIFY a BIT COLLATE unknown; BOGUS; END';
        $statements[] = "SHOW SESSION STATUS LIKE 'Com_alter_table'";
        $statements[] = "SHOW SESSION STATUS LIKE 'Com_create_table'";
        $statements[] = "SHOW SESSION STATUS LIKE 'Com_create_procedure'";
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testColumnRolesRetainPreparationOrderAndStoredDeclarationFailures(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
