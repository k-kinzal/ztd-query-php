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
final class DeleteTargetDatabaseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE delete_probe',
            'CREATE TABLE delete_probe.t(a INT)',
            'DELETE x FROM (SELECT 1) AS y',
            'DELETE y FROM (SELECT 1) AS y',
            'DELETE delete_probe.x FROM (SELECT 1) AS y',
            'DELETE x FROM delete_probe.t AS y',
            'DELETE y FROM delete_probe.t AS y',
            'DELETE delete_probe.x FROM delete_probe.t AS y',
            'DELETE x FROM missing AS y',
            'DELETE x FROM (SELECT ABS()) AS y',
            'DELETE x FROM (SELECT 1) AS y, (SELECT 2) AS y',
            'DELETE FROM x USING (SELECT 1) AS y',
            'USE delete_probe',
            'DELETE x FROM (SELECT 1) AS y',
            'DELETE y FROM (SELECT 1) AS y',
            'DELETE delete_probe.x FROM (SELECT 1) AS y',
            'DELETE x FROM delete_probe.t AS y',
            'DELETE y FROM delete_probe.t AS y',
            'DELETE delete_probe.x FROM delete_probe.t AS y',
            'DELETE x FROM missing AS y',
            'DELETE x FROM (SELECT ABS()) AS y',
            'DELETE x FROM (SELECT 1) AS y, (SELECT 2) AS y',
            'DELETE FROM x USING (SELECT 1) AS y',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testUnqualifiedTargetsRequireTheDatabaseBeforeReadingSourceRelations(string $release, array $statements): void
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
