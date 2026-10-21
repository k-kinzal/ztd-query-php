<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class KeyCacheStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     * @throws JsonException When the saved native input cannot be decoded
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/key-cache-state.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Named cache initialization, buffer removal, case-sensitive identities and populated engine-specific index reports
     */
    #[DataProvider('providerReleases')]
    public function testCacheIdentityFollowsGlobalAllocationHistoryAndTheTableEngine(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->query('CREATE DATABASE cache_fixture');
                $native->select_db('cache_fixture');
                $memory->query('CREATE DATABASE cache_fixture');
                $memory->select_db('cache_fixture');
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);

                self::assertSame($expected, $actual, $release);
                self::assertSame(serialize(['results' => [['affected' => 2, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => false]), $expected[3]);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
