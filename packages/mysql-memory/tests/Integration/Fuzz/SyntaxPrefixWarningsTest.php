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
        array_push($statements, "SELECT N'a',?", "SELECT N'a' 'b',?", "SELECT BINARY (N'a'+?),2", "SELECT BINARY N'a',?", "SELECT N'a', N'b',?", "SELECT ) N'a',?", "SELECT N'a', 'unterminated");
        array_push($statements, 'SELECT 1 COLLATE unknown_a,?', 'SELECT 1 COLLATE unknown_a COLLATE unknown_b,?', 'SELECT !1 COLLATE unknown_a, BINARY 1,?', "SELECT CONVERT('a' USING unknown_a),?", 'SELECT CAST(1 AS CHAR CHARACTER SET unknown_a),?', 'SELECT ? COLLATE unknown_a', 'SELECT 1 COLLATE unknown_a +', 'SELECT 1 COLLATE unknown_a COLLATE unknown_a,?', "SELECT 1 COLLATE 'a''b',?", "SELECT 1 COLLATE '',?", 'SELECT 1 COLLATE utf8mb4_bin,?', 'SELECT 1 COLLATE latin1_bin,?', 'SELECT 1 COLLATE unknown_a, CONVERT(1 USING unknown_b),?', 'SELECT CONVERT(1 USING unknown_a), 1 COLLATE unknown_b,?', "SELECT CAST(1 AS CHAR CHARACTER SET ''),?", "SELECT 1 COLLATE 'no\\pe',?", 'CREATE TABLE t (a INT) COLLATE unknown_a,?', 'CREATE TABLE t (a INT) CHARSET unknown_a,?');
        array_push($statements, 'SELECT CAST(1 AS DATETIME(7)),?', 'SELECT CAST(1 AS DATETIME(7),?', 'SELECT CAST(1 AS FLOAT(54)),?', 'SELECT CAST(1 AS JSON ARRAY),?', 'SELECT CAST(1 AS JSON ARRAY,?', 'SELECT CAST(1 AT LOCAL AS DATE),?', 'SELECT CAST(1 AT LOCAL AS DATETIME(7)),?', "SELECT CAST(1 AT TIME ZONE '+00:00' AS DATETIME(7)),?", 'SELECT CONVERT(1,DATETIME(7)),?', 'SELECT CAST(1 AS NCHAR),?', 'SELECT CAST(1 AS NCHAR ARRAY),?', 'SELECT CAST(1 AS NCHAR,?', 'SELECT !CAST(1 AS DATETIME(7)), !?', 'SELECT 1 COLLATE missing, CAST(1 AS DATETIME(7)),?', "CREATE TABLE t (a INT) COLLATE '',?");
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['', 'PIPES_AS_CONCAT', 'NO_BACKSLASH_ESCAPES'] as $mode) {
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
