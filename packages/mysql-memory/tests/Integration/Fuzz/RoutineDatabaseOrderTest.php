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
final class RoutineDatabaseOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [
                'SELECT missing, f()',
                'SELECT missing, f() FROM missing',
                'SELECT missing, f() FROM d.missing',
                'SELECT missing, d.f()',
                'SELECT ROW(1,2), f()',
                'SELECT ABS(), f()',
                'SELECT f() AS n',
            ];
            if (!str_starts_with($release, '5.')) {
                array_push(
                    $statements,
                    "SELECT JSON_VALUE(1, ''), f()",
                    "SELECT JSON_VALUE(1, ''), f() FROM d.missing",
                    "SELECT COALESCE(JSON_VALUE(NULL, ''), MATCH (``) AGAINST (`あ`() IN NATURAL LANGUAGE MODE), DATE_ADD(NULL, INTERVAL FALSE HOUR))",
                    "WITH c AS (SELECT f()) SELECT JSON_VALUE(1, '')",
                    'WITH c AS (SELECT ROW(1,2), f()) SELECT 1',
                    'WITH c AS (SELECT ROW(1,2), f()) SELECT 1 FROM c',
                );
            }
            array_push($statements, 'CREATE DATABASE d', 'USE d', 'SELECT missing, f()', 'SELECT missing, f() FROM missing', 'SELECT f() AS n', 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 7', 'SELECT f() AS n', 'DROP FUNCTION f', 'SELECT f() AS n');
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Routine database dependencies before and after selecting and populating a schema
     */
    #[DataProvider('providerReleases')]
    public function testImplicitRoutineDatabasesResolveBeforeOperandPreparation(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && (str_starts_with($sql, 'WITH ') || str_starts_with($sql, 'SELECT ')))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && (str_starts_with($sql, 'WITH ') || str_starts_with($sql, 'SELECT ')))), $statements);

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
