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
final class QueryTailTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t(a INT)',
            'INSERT INTO t VALUES (1)',
            'SELECT (SELECT 1 LIMIT 1 LIMIT 2)',
            'SELECT (SELECT 1 FROM DUAL LIMIT 1 LIMIT 2)',
            'SELECT (SELECT 1 ORDER BY 1 LIMIT 1 LIMIT 2)',
            'SELECT (SELECT 1 FROM DUAL ORDER BY 1 LIMIT 1 LIMIT 2)',
            'SELECT (SELECT a FROM t ORDER BY a LIMIT 1 LIMIT 2)',
            'SELECT (SELECT a FROM t ORDER BY a LIMIT 1 FOR UPDATE LIMIT 2)',
            'SELECT (SELECT 1 FOR UPDATE LIMIT 1)',
            'SELECT (SELECT 1 FROM DUAL FOR UPDATE LIMIT 1)',
            'SELECT (SELECT 1 FROM DUAL LIMIT 1 ORDER BY 1 LIMIT 2)',
            'SELECT (SELECT 1 LIMIT 1 ORDER BY 1)',
            'SELECT (SELECT 1 ORDER BY 1 FOR UPDATE LIMIT 1)',
            'SELECT (SELECT 1 FROM DUAL ORDER BY 1 FOR UPDATE LIMIT 1)',
            'SHOW BINLOG EVENTS FROM 0x3',
            'SHOW RELAYLOG EVENTS FROM 0x3',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The valid and refused query-tail histories to compare
     */
    #[DataProvider('providerReleases')]
    public function testTailPositionsRetainNativeBehavior(string $release, array $statements): void
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
