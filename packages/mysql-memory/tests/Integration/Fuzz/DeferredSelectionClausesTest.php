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
final class DeferredSelectionClausesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE fixture(a INT)', 'INSERT INTO fixture VALUES(1),(2)'];
            $queries = ['SELECT CONVERT(1 USING ucs2), CONVERT((1,2) USING utf8) FROM fixture'];
            foreach (['(1,2)', '(a,2)', '(missing,2)'] as $row) {
                array_push($queries, 'SELECT DISTINCT ' . $row . ' AS v FROM fixture', 'SELECT ' . $row . ' AS v FROM fixture ORDER BY 1 LIMIT 0', 'SELECT ' . $row . ' AS v FROM fixture GROUP BY a HAVING 0', 'SELECT a AS v FROM fixture WHERE ' . $row, 'SELECT a AS v FROM fixture GROUP BY ' . $row, 'SELECT a AS v FROM fixture HAVING ' . $row, 'SELECT a AS v FROM fixture ORDER BY ' . $row, 'SELECT a AS v FROM fixture WHERE 0 ORDER BY ' . $row, 'SELECT a AS v FROM fixture WHERE missing ORDER BY ' . $row, 'SELECT missing AS v FROM fixture HAVING ' . $row, 'SELECT a AS v FROM fixture GROUP BY a HAVING ' . $row, 'SELECT a AS v FROM fixture ORDER BY v', 'SELECT a AS v FROM fixture HAVING (v,2)', 'SELECT a AS v FROM fixture ORDER BY (v,2)');
            }
            if (!str_starts_with($release, '5.')) {
                $zone = "CAST(1 AT TIME ZONE '+00:00' AS DATETIME)";
                array_push($queries, 'SELECT DISTINCT ' . $zone . ' FROM fixture', 'SELECT ' . $zone . ' FROM fixture LIMIT 0', 'SELECT ' . $zone . ' FROM fixture GROUP BY a', 'SELECT 1 HAVING ' . $zone, 'SELECT 1 ORDER BY ' . $zone);
            }
            foreach ($queries as $index => $query) {
                $name = 'p' . $index;
                array_push($statements, $query, 'SELECT a FROM fixture LIMIT 0', 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN ' . $query . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()');
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testSelectionClausesRetainTheirPreparationRoles(string $release, array $statements): void
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
