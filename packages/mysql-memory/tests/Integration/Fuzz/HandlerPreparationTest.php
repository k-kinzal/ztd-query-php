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
final class HandlerPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $reads = [];
        foreach (['missing()', 'd.missing()', 'ABS(missing())', 'missing_column + missing()', 'ABS(1)', 'missing_column', 'COUNT(*)'] as $expression) {
            $reads[] = 'HANDLER h READ NEXT WHERE ' . $expression;
            $reads[] = 'HANDLER h READ `PRIMARY` = (' . $expression . ')';
        }
        $reads[] = 'HANDLER h READ NEXT WHERE missing() LIMIT bad';
        $reads[] = 'HANDLER h READ NEXT WHERE d.missing() LIMIT bad';
        $statements = [...$reads, 'CREATE DATABASE d', 'USE d', ...$reads,
            'CREATE TABLE t(a INT PRIMARY KEY)', 'INSERT INTO t VALUES(1),(2)', 'HANDLER t OPEN AS h', ...$reads,
            'CREATE FUNCTION missing() RETURNS INT DETERMINISTIC RETURN 1', ...$reads,
            'DROP FUNCTION missing', ...$reads, 'HANDLER h CLOSE', ...$reads];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testHandlerPreparationPreservesFunctionAndCursorHistory(string $release, array $statements): void
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
