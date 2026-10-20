<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UpdateTargetInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $writes = [
            'UPDATE (SELECT a FROM t) q SET a=3',
            'WITH q AS (SELECT a FROM t) UPDATE q SET a=4',
            'UPDATE t,u SET t.a=1 ORDER BY t.a LIMIT 1',
            'UPDATE t,u SET missing=1 LIMIT 1',
            "UPDATE JSON_TABLE('[1]', '$[*]' COLUMNS(a INT PATH '$')) j SET missing=7",
            "UPDATE JSON_TABLE('[1]', '$[*]' COLUMNS(a INT PATH '$')) j JOIN t ON TRUE SET t.a=8",
            "UPDATE JSON_TABLE('[1]', '$[*]' COLUMNS(a INT PATH '$')) j JOIN t ON TRUE SET j.a=9",
        ];
        $statements = ['CREATE DATABASE d', 'USE d', ...$writes, 'CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)', 'INSERT INTO t VALUES(1),(2)', 'INSERT INTO u VALUES(3)', ...$writes, 'SELECT * FROM t', "PREPARE s FROM 'UPDATE (SELECT a FROM t) q SET a=2'", 'EXECUTE s', 'DROP TABLE t', ...$writes];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Read-only and physical destinations before and after catalog and row setup
     */
    #[DataProvider('providerStatements')]
    public function testUpdateTargetRefusalsRetainNativePreparationOrder(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
