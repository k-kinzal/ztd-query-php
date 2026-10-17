<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FlushQueryCacheTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testQueryCacheNoticesMatchForParsedAndConstructedRequests(string $release): void
    {
        $statements = [
            'FLUSH QUERY CACHE', 'FLUSH QUERY CACHE, QUERY CACHE', 'FLUSH HOSTS, QUERY CACHE',
            'FLUSH QUERY CACHE,', 'FLUSH QUERY CACHE, ?', "FLUSH QUERY CACHE, RELAY LOGS FOR CHANNEL 'absent'",
            'CREATE DATABASE cache_probe', 'USE cache_probe',
            'CREATE PROCEDURE p() FLUSH QUERY CACHE', 'SELECT 1', 'CALL p()',
            'CREATE PROCEDURE p2() BEGIN IF FALSE THEN FLUSH QUERY CACHE; END IF; END', 'SELECT 1', 'CALL p2()',
        ];
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

                $native->close();
                $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
                $node = new Flush(false, [new FlushItem(FlushOption::QueryCache), new FlushItem(FlushOption::QueryCache)]);
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid request';
                self::assertSame(serialize((new Native())->observe($native, 'FLUSH QUERY CACHE, QUERY CACHE')), serialize((new Memory())->observe($session, $node)));
                self::assertSame('unrelated invalid request', $session->text);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
