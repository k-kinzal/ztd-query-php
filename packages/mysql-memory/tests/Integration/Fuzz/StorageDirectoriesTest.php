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
final class StorageDirectoriesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ["CREATE TABLE no_database DATA DIRECTORY 'text'", "CREATE TABLE absent.no_database DATA DIRECTORY 'text'", 'CREATE DATABASE directory_domain', 'USE directory_domain', 'CREATE TABLE existing(a INT)'];
        foreach (['DATA', 'INDEX'] as $kind) {
            foreach (['t', 'absent.t', 'IF NOT EXISTS existing'] as $table) {
                foreach (['', '(a INT)', 'AS SELECT missing', 'AS SELECT 1'] as $body) {
                    $statements[] = 'CREATE TABLE ' . $table . ' ' . (str_starts_with($body, 'AS') ? '' : $body) . ' ' . $kind . " DIRECTORY 'text' " . (str_starts_with($body, 'AS') ? $body : '');
                }
            }
            foreach (['ENGINE=missing_engine', 'DEFAULT CHARSET=missing_charset', 'DEFAULT COLLATE=missing_collation'] as $option) {
                $statements[] = 'CREATE TABLE t ' . $kind . " DIRECTORY 'text' " . $option;
                $statements[] = 'CREATE TABLE t ' . $option . ' ' . $kind . " DIRECTORY 'text'";
            }
        }
        array_push($statements, ...array_merge(...array_map(static fn (string $body, int $index): array => ['CREATE PROCEDURE p' . $index . '(run_it INT) BEGIN IF run_it THEN ' . $body . '; END IF; END', 'CALL p' . $index . '(0)', 'CALL p' . $index . '(1)', 'CALL p' . $index . '(0)'], ["CREATE TABLE t DATA DIRECTORY 'text'", "CREATE TABLE t DATA DIRECTORY 'text' AS SELECT missing", "CREATE TABLE t (a INT) INDEX DIRECTORY 'text'"], [0, 1, 2])));
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testDirectoryConditionsPrecedeDestinationAndColumnPreparation(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
