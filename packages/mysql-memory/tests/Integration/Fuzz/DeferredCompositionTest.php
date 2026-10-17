<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeferredCompositionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE fixture(a INT)', 'INSERT INTO fixture VALUES(1),(2)'];
            $expressions = ['CAST((1,2) AS CHAR)', 'CAST((missing,2) AS CHAR)', 'CONVERT((1,2) USING utf8mb4)', 'CONVERT((missing,2) USING utf8mb4)', 'IF(0,(1,2),NULL)', 'IF((1,2),0,1)', 'CASE WHEN 0 THEN (1,2) ELSE NULL END', 'CASE (1,2) WHEN 1 THEN 0 END', 'CASE WHEN (1,2) THEN 0 END'];
            if (!str_starts_with($release, '5.')) {
                $zone = "CAST(1 AT TIME ZONE '+00:00' AS DATETIME)";
                array_push($expressions, 'CONVERT(' . $zone . ' USING utf8mb4)', 'IF(0,' . $zone . ',NULL)', 'CASE WHEN 0 THEN ' . $zone . ' ELSE NULL END', 'CASE ' . $zone . ' WHEN 1 THEN 0 END');
            }
            foreach ($expressions as $index => $expression) {
                $name = 'p' . $index;
                array_push($statements, 'SELECT ' . $expression, 'SELECT a FROM fixture LIMIT 0', 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN SELECT ' . $expression . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()');
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testComposedOperandsPreservePreparationAndInvocationRoles(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
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
