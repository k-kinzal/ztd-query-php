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
final class ShowLimitPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ["SELECT CAST('x' AS UNSIGNED)", 'SHOW WARNINGS LIMIT a', 'SHOW BINLOG EVENTS LIMIT LEVEL OFFSET `name`'];
        foreach (['SHOW BINLOG EVENTS', 'SHOW RELAYLOG EVENTS', "SHOW BINLOG EVENTS IN 'missing'", "SHOW RELAYLOG EVENTS IN 'missing'", 'SHOW PROFILE', 'SHOW WARNINGS', 'SHOW ERRORS'] as $show) {
            foreach (['a OFFSET b', 'a, b', '0 OFFSET b', 'a OFFSET 0'] as $limit) {
                $statements[] = "$show LIMIT $limit";
            }
        }
        $statements[] = 'CREATE DATABASE d';
        $statements[] = 'USE d';
        $statements[] = 'CREATE PROCEDURE p(a INT, b INT) SHOW BINLOG EVENTS LIMIT a OFFSET b';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$statements, version_compare($release, '8.4.0', '>=') ? 'RESET BINARY LOGS AND GTIDS' : 'RESET MASTER', 'CALL p(0,0)', 'DROP PROCEDURE p']];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testLimitNamesFollowTheirWrittenOrderBeforeLogLookup(string $release, array $statements): void
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
