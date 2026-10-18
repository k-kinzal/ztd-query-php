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
final class DeletePrefixPhaseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'WITH c AS (SELECT 1), c AS (SELECT 2) DELETE x FROM t',
            'WITH c AS (SELECT 1), c AS (SELECT 2) DELETE FROM x USING t',
            'WITH c AS (SELECT 1), c AS (SELECT 2) DELETE x,x FROM t',
            'WITH c AS (SELECT 1), c AS (SELECT 2) DELETE FROM x USING t WHERE ?',
            'WITH unused AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) DELETE x FROM t',
            'WITH unused AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) DELETE FROM x USING t',
            'WITH unused AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) DELETE x,x FROM t',
            'WITH unused AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) DELETE FROM x USING t WHERE ?',
            'WITH c AS (SELECT SQL_NO_CACHE 1), c AS (SELECT 2) DELETE x FROM t',
            'WITH c AS (SELECT SQL_NO_CACHE 1), c AS (SELECT 2) DELETE FROM x USING t',
            'WITH c AS (SELECT SQL_NO_CACHE 1), c AS (SELECT 2) DELETE x,x FROM t',
            'WITH c AS (SELECT SQL_NO_CACHE 1), c AS (SELECT 2) DELETE FROM x USING t WHERE ?',
        ];
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/delete-cte-prefix.sql');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements CTE grammar conditions before target namespace resolution
     */
    #[DataProvider('providerReleases')]
    public function testSyntaxConditionsSurviveLaterTargetRefusal(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);

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
