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
final class DeleteTargetOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $cases = ['DELETE a,a FROM t a', 'DELETE a,a FROM missing a', 'DELETE a,b,a FROM t a', 'DELETE d.a,d.a FROM t a', 'DELETE d.a,d.a,b FROM t a', 'DELETE d.a,b,d.a FROM t a', 'DELETE a,A FROM t a', 'DELETE FROM a,a USING t a'];
        $suffixes = ['DELETE a,a FROM', 'DELETE d.a,d.a FROM', 'DELETE FROM d.a,d.a USING', 'DELETE a,a FROM t a WHERE', 'DELETE d.a,d.a FROM t a WHERE'];
        $stored = array_merge(...array_map(static fn (string $body): array => ['CREATE PROCEDURE p() ' . $body, 'CALL p()', 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF 0 THEN ' . $body . '; END IF; END', 'CALL p()', 'DROP PROCEDURE IF EXISTS p'], $cases));
        $statements = [...$cases, ...$suffixes, 'CREATE PROCEDURE p() DELETE d.a,d.a FROM d.t a', 'CREATE PROCEDURE missing.p() DELETE a,a FROM d.t a', 'CREATE DATABASE d', 'CREATE TABLE d.t(a INT)', 'INSERT INTO d.t VALUES(7)', 'CREATE PROCEDURE d.p() DELETE a,a FROM d.t a', 'USE d', ...$cases, ...$suffixes, ...$stored, 'SELECT * FROM t'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testRepeatedTargetsFollowDatabaseAndStoredDeclarationOrder(string $release, array $statements): void
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
