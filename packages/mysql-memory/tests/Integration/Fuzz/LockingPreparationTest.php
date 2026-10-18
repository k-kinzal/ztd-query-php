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
final class LockingPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            '(TABLE missing FOR UPDATE) FOR UPDATE',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) (TABLE missing FOR UPDATE) FOR UPDATE',
            'SELECT ROW(1,2) LIKE 1 FROM missing FOR UPDATE FOR SHARE',
            'SELECT 1 FROM missing FOR UPDATE OF absent',
            'SELECT ROW(1,2) LIKE 1 FROM missing FOR UPDATE OF absent',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1 FROM c FOR UPDATE FOR SHARE',
            'WITH c AS (SELECT 1) SELECT 1 FROM c FOR UPDATE FOR SHARE',
            'WITH c AS (SELECT 1) SELECT 1 FROM c FOR UPDATE OF c FOR SHARE OF c',
            'SELECT 1 WHERE ROW(1,2) LIKE 1 FOR UPDATE OF absent',
            'SELECT 1 WHERE ROW(1,2) LIKE 1 FOR UPDATE FOR SHARE',
        ];
        $statements = [...$statements,
            'WITH c AS (SELECT 1 FROM c FOR UPDATE FOR SHARE) SELECT 1',
            'WITH a AS (SELECT 1 FROM c FOR UPDATE FOR SHARE), c AS (SELECT 1) SELECT 1',
            'WITH c AS (SELECT 1), a AS (SELECT 1 FROM c FOR UPDATE FOR SHARE) SELECT 1',
            'WITH c AS (SELECT 1 FROM absent FOR UPDATE FOR SHARE) SELECT 1',
            'WITH RECURSIVE c AS (SELECT 1 FROM c FOR UPDATE FOR SHARE) SELECT 1',
            'WITH RECURSIVE a AS (SELECT 1 FROM c FOR UPDATE FOR SHARE), c AS (SELECT 1) SELECT 1',
            'WITH RECURSIVE c AS (SELECT 1), a AS (SELECT 1 FROM c FOR UPDATE FOR SHARE) SELECT 1',
            'WITH RECURSIVE c AS (SELECT 1 FROM absent FOR UPDATE FOR SHARE) SELECT 1',
        ];
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/locking-refused-operand.sql');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Lock targets and deferred query operands
     */
    #[DataProvider('providerReleases')]
    public function testLockScopesRetainConditionsBeforeDeferredOperandPreparation(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && !str_starts_with($sql, 'CREATE DATABASE') && !str_starts_with($sql, 'USE '))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && !str_starts_with($sql, 'CREATE DATABASE') && !str_starts_with($sql, 'USE '))), $statements);

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
