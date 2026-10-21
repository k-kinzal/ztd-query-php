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
final class WriteHeadOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $writes = ['REPLACE t SET a=ABS()', 'UPDATE t SET a=ABS()', 'INSERT INTO t SELECT ABS()', 'INSERT INTO t VALUES(ABS())', 'UPDATE d.t JOIN u SET t.a=ABS()', 'UPDATE d.t JOIN (SELECT 1 FROM u) c SET t.a=ABS()', 'WITH c AS(SELECT 1) UPDATE d.t JOIN c SET t.a=ABS()', 'WITH c AS(SELECT ABS()) UPDATE d.t JOIN c SET t.a=1', 'UPDATE t SET a=CAST(1 AS TIME(7))', 'UPDATE t SET a=ABS() WHERE', 'REPLACE d.t SET a=ABS()', 'SELECT ABS() FROM t',
            'WITH c AS(SELECT 1 FROM u) UPDATE d.t JOIN c SET t.a=ABS()',
            'WITH c AS(SELECT 1 FROM u) UPDATE d.t SET t.a=ABS()',
            'UPDATE d.t JOIN (WITH c AS(SELECT 1) SELECT * FROM c) x SET t.a=ABS()',
            'WITH c AS(SELECT 1 FROM u), e AS(SELECT * FROM c) UPDATE d.t JOIN e SET t.a=ABS()',
            'WITH c AS(SELECT * FROM e), e AS(SELECT 1 FROM u) UPDATE d.t JOIN c SET t.a=ABS()',
            'UPDATE d.t JOIN (WITH c AS(SELECT 1 FROM u) SELECT ABS() FROM c) x SET t.a=1',
        ];
        $statements = [...$writes, 'CREATE DATABASE d', 'CREATE TABLE d.t(a INT)', ...$writes, 'USE d', ...$writes];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, false];
            yield $release . ':prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Write dependencies before refused call operands, with and without a selected database
     */
    #[DataProvider('providerReleases')]
    public function testWriteHeadsPreserveNativeInputConditionsAndDatabaseResolution(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
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
