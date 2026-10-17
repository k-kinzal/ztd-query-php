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
final class PairedNegationsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SELECT !!1', 'SELECT !!!1', 'SELECT !!!!1', 'SELECT !!!!!1', 'SELECT ! !1', 'SELECT !/**/!1', 'SELECT !!NULL', 'SELECT !!0', 'SELECT !!-3', 'SELECT !!0.1', "SELECT !!'abc'", "SELECT !!'1.5foo'", 'SELECT !!(1 OR 0)', 'SELECT !!(NULL OR 0)', 'SELECT !!(1 IS NULL)', 'SELECT !!(1=2)', 'SELECT !!(1 AND 2)', 'SELECT !!(NULL XOR 0)', 'SELECT !!(1 BETWEEN 0 AND 2)', 'SELECT !!(1 IN(1,2))', 'SELECT !!1 AS v', 'SELECT !! @a:=1', 'SELECT ! ! !1', 'SELECT !!! !1', 'SELECT ! !!1', 'SELECT !! !1', 'CREATE DATABASE pair_values', 'USE pair_values', 'CREATE TABLE t (v INT)', 'CREATE VIEW v AS SELECT !!v AS a FROM t', 'SHOW CREATE VIEW v', 'INSERT INTO t VALUES (0),(1),(NULL)', 'SELECT * FROM v ORDER BY a'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['', 'HIGH_NOT_PRECEDENCE'] as $mode) {
                yield $release . ' ' . $mode => [$release, $statements, $mode];
            }
        }
    }

    /**
     * @param list<string> $statements Negation values, warnings, metadata and stored definitions
     */
    #[DataProvider('providerReleases')]
    public function testPairedNegationsMatchTheNativeServer(string $release, array $statements, string $mode): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $native->exec("SET SESSION sql_mode='" . $mode . "'");
                $memory->exec("SET SESSION sql_mode='" . $mode . "'");
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release . ' ' . $mode);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
