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
final class SubqueryModifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [];
            foreach (['HIGH_PRIORITY', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS'] as $option) {
                foreach (['SELECT (%s)', 'SELECT EXISTS (%s)', 'SELECT 1 IN (%s)', 'SELECT 1 = ANY (%s)', 'SELECT * FROM (%s) AS d'] as $format) {
                    $statements[] = sprintf($format, 'SELECT ' . $option . ' missing FROM absent');
                    $statements[] = sprintf($format, 'SELECT ALL DISTINCT ' . $option . ' 1');
                }
                $statements[] = '(SELECT ' . $option . ' 1)';
                if (version_compare($release, '8.0', '>=')) {
                    $statements[] = 'WITH c AS (SELECT ' . $option . ' 1) SELECT 2';
                }
            }
            $statements = [...$statements, 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t (a INT)', 'INSERT INTO t VALUES (1),(2)'];
            foreach (['HIGH_PRIORITY', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS'] as $option) {
                $statements[] = 'SELECT ' . $option . ' a FROM t ORDER BY a';
                $statements[] = 'CREATE PROCEDURE p() BEGIN IF FALSE THEN SELECT (SELECT ' . $option . ' a FROM t); END IF; END';
                $statements[] = 'DROP PROCEDURE IF EXISTS p';
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testNestedModifiersAndUnreachableInstructionsMatchNative(string $release, array $statements): void
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
