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
final class SyntaxPrefixWarningsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SELECT !?', 'SELECT !1,?', 'SELECT !!?', 'SELECT !!!?', 'SELECT ! !?', 'SELECT !(!?)', 'SELECT 1 || ?', 'SELECT 1 && ?', 'SELECT SQL_NO_CACHE ?', 'SELECT SQL_CALC_FOUND_ROWS ?', 'SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS !?', 'SELECT BINARY ?', 'SELECT BINARY 1,?', 'SELECT BINARY 1 ?', 'SELECT BINARY (1+?),2', 'SELECT BINARY 1+?', 'SELECT @a:=1+?', 'SELECT @a:=BINARY 1,?', 'SELECT BINARY BINARY 1,?', 'SELECT BINARY 1 FROM ?', 'SELECT BINARY 1 UNION SELECT ?', 'SELECT @a:=1 FROM ?', 'SELECT ! FROM t', 'SELECT 1 || FROM t', 'SELECT 1 && FROM t', 'SELECT SQL_NO_CACHE FROM t', 'SELECT 1, SQL_NO_CACHE ?', 'SELECT SQL_NO_CACHE SQL_NO_CACHE ?', 'SELECT @a := ?', 'SELECT @a := 1, ?', 'SELECT SQL_NO_CACHE (SELECT SQL_NO_CACHE !?),?', 'SELECT /*! SQL_NO_CACHE */ !?', 'SELECT \'SQL_NO_CACHE !\', ?', 'SELECT SQL_NO_CACHE !1, \'unterminated', 'SELECT !1, 0x', 'SELECT 1, ) !1, ?', 'SELECT SQL_CALC_FOUND_ROWS (SELECT SQL_NO_CACHE BINARY 1),?', 'SELECT @a:=(SELECT BINARY 1),?'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['', 'PIPES_AS_CONCAT'] as $mode) {
                yield $release . ' ' . $mode => [$release, $statements, $mode];
            }
        }
    }

    /**
     * @param list<string> $statements The ordered syntax-warning regressions
     */
    #[DataProvider('providerReleases')]
    public function testWarningsBeforeSyntaxRefusalsMatchTheNativeServer(string $release, array $statements, string $mode): void
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
