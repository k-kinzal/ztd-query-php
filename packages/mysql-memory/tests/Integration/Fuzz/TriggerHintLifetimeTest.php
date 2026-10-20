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
final class TriggerHintLifetimeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{int, string, bool}>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $requests = [[0, 'CREATE DATABASE d'], [0, 'CREATE TABLE d.t(a INT)'], [0, 'SELECT * FROM d.t'], [0, 'CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET NEW.a=(SELECT /*+ RESOURCE_GROUP(a) */ 1)'], [0, 'SELECT * FROM d.t'], [1, 'SELECT * FROM d.t'], [0, 'FLUSH TABLES d.t'], [1, 'SELECT * FROM d.t'], [0, 'SELECT * FROM d.t'], [0, 'UPDATE d.t SET a=2'], [0, 'INSERT INTO d.t VALUES(3),(4)'], [1, 'INSERT INTO d.t VALUES(5)'], [0, 'CREATE TRIGGER d.tr2 BEFORE UPDATE ON d.t FOR EACH ROW SET NEW.a=(SELECT /*+ RESOURCE_GROUP(b) */ 2)'], [0, 'SELECT * FROM d.t'], [1, 'SELECT * FROM d.t'], [0, 'DROP TRIGGER d.tr2'], [0, 'SELECT * FROM d.t'], [0, 'FLUSH TABLES'], [0, 'SELECT bad FROM d.t'], [0, 'SELECT * FROM d.t'], [0, 'UPDATE d.t SET absent=2'], [0, 'SELECT * FROM d.t']];
            $queries = array_map(static fn (array $request): array => [...$request, false], $requests);
            array_push($queries, [0, 'FLUSH TABLES', false], [0, 'SELECT * FROM d.t', true], [0, 'INSERT INTO d.t VALUES(6)', true], [0, 'SELECT * FROM d.t', false]);
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<array{int, string, bool}> $queries Connection, SQL and protocol flag
     */
    #[DataProvider('providerHistories')]
    public function testWarningsFollowConnectionTableAndDefinitionLifetimes(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $server = Server::start($release);
            try {
                $native = array_map(static fn (): mysqli => new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port), [0, 1]);
                $memory = array_map(static fn (): mysqli => new mysqli($server->host, 'root', '', port: $server->port), [0, 1]);
                array_map(static fn (mysqli $connection): bool => $connection->set_charset('utf8mb4'), [...$native, ...$memory]);
                $trials = array_map(static fn (array $query): array => [serialize((new Native())->observe($native[$query[0]], $query[1], prepared: $query[2])), serialize((new Native())->observe($memory[$query[0]], $query[1], prepared: $query[2]))], $queries);
                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                array_map(static fn (mysqli $connection): bool => $connection->close(), [...$native, ...$memory]);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
