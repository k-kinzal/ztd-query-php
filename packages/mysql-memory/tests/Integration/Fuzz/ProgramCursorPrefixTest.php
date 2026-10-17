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
final class ProgramCursorPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['CREATE PROCEDURE p() ', 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO ', 'ALTER EVENT e DO '] as $header) {
            foreach (['OPEN c;', 'CLOSE c;', 'FETCH c INTO x;', 'DECLARE x INT; FETCH c INTO x;', 'DECLARE c CURSOR FOR SELECT 1; OPEN c;', 'DECLARE c CURSOR FOR SELECT 1; BEGIN CLOSE c; END;', 'BEGIN DECLARE c CURSOR FOR SELECT 1; END; OPEN c;', 'LOOP OPEN c; END LOOP;', 'DECLARE c CURSOR FOR SELECT 1; LOOP OPEN c; END LOOP;'] as $prefix) {
                $statements[] = $header . 'BEGIN ' . $prefix . " SIGNAL SQLSTATE '00000'; END";
            }
        }
        $statements = ['CREATE DATABASE d', ...$statements, 'USE d', ...$statements, 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DO 0', ...$statements];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$statements, ...(version_compare($release, '8.0', '>=') ? ['ALTER EVENT e DO LOOP OPEN c; IF CAST(1 AT LOCAL AS SIGNED) THEN DO 0; END IF; END LOOP', 'CREATE PROCEDURE p() BEGIN OPEN c; SELECT CAST(1 AT LOCAL AS SIGNED); END'] : [])]];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testEarlierCursorNamesResolveBeforeLaterRejectedValues(string $release, array $statements): void
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
