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
final class LegacyUpdateOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES (1),(2)', 'UPDATE (SELECT SQL_CACHE ?) AS x SET a=1', 'UPDATE ((SELECT SQL_CACHE ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT SQL_CACHE ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT SQL_CACHE ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT SQL_CACHE ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT SQL_CACHE ?)', 'DELETE FROM t WHERE a=(SELECT SQL_CACHE ?)', 'SELECT * FROM (SELECT SQL_CACHE ?) x', 'INSERT INTO t SELECT SQL_CACHE ?', 'CREATE TABLE z AS SELECT SQL_CACHE ?', 'UPDATE (SELECT SQL_NO_CACHE ?) AS x SET a=1', 'UPDATE ((SELECT SQL_NO_CACHE ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT SQL_NO_CACHE ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT SQL_NO_CACHE ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT SQL_NO_CACHE ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT SQL_NO_CACHE ?)', 'DELETE FROM t WHERE a=(SELECT SQL_NO_CACHE ?)', 'SELECT * FROM (SELECT SQL_NO_CACHE ?) x', 'INSERT INTO t SELECT SQL_NO_CACHE ?', 'CREATE TABLE z AS SELECT SQL_NO_CACHE ?', 'UPDATE (SELECT HIGH_PRIORITY ?) AS x SET a=1', 'UPDATE ((SELECT HIGH_PRIORITY ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT HIGH_PRIORITY ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT HIGH_PRIORITY ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT HIGH_PRIORITY ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT HIGH_PRIORITY ?)', 'DELETE FROM t WHERE a=(SELECT HIGH_PRIORITY ?)', 'SELECT * FROM (SELECT HIGH_PRIORITY ?) x', 'INSERT INTO t SELECT HIGH_PRIORITY ?', 'CREATE TABLE z AS SELECT HIGH_PRIORITY ?', 'UPDATE (SELECT SQL_BUFFER_RESULT ?) AS x SET a=1', 'UPDATE ((SELECT SQL_BUFFER_RESULT ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT SQL_BUFFER_RESULT ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT SQL_BUFFER_RESULT ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT SQL_BUFFER_RESULT ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT SQL_BUFFER_RESULT ?)', 'DELETE FROM t WHERE a=(SELECT SQL_BUFFER_RESULT ?)', 'SELECT * FROM (SELECT SQL_BUFFER_RESULT ?) x', 'INSERT INTO t SELECT SQL_BUFFER_RESULT ?', 'CREATE TABLE z AS SELECT SQL_BUFFER_RESULT ?', 'UPDATE (SELECT SQL_CALC_FOUND_ROWS ?) AS x SET a=1', 'UPDATE ((SELECT SQL_CALC_FOUND_ROWS ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT SQL_CALC_FOUND_ROWS ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT SQL_CALC_FOUND_ROWS ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT SQL_CALC_FOUND_ROWS ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT SQL_CALC_FOUND_ROWS ?)', 'DELETE FROM t WHERE a=(SELECT SQL_CALC_FOUND_ROWS ?)', 'SELECT * FROM (SELECT SQL_CALC_FOUND_ROWS ?) x', 'INSERT INTO t SELECT SQL_CALC_FOUND_ROWS ?', 'CREATE TABLE z AS SELECT SQL_CALC_FOUND_ROWS ?', 'UPDATE (SELECT SQL_BIG_RESULT SQL_CACHE ?) AS x SET a=1', 'UPDATE ((SELECT SQL_BIG_RESULT SQL_CACHE ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT SQL_BIG_RESULT SQL_CACHE ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT SQL_BIG_RESULT SQL_CACHE ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT SQL_BIG_RESULT SQL_CACHE ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT SQL_BIG_RESULT SQL_CACHE ?)', 'DELETE FROM t WHERE a=(SELECT SQL_BIG_RESULT SQL_CACHE ?)', 'SELECT * FROM (SELECT SQL_BIG_RESULT SQL_CACHE ?) x', 'INSERT INTO t SELECT SQL_BIG_RESULT SQL_CACHE ?', 'CREATE TABLE z AS SELECT SQL_BIG_RESULT SQL_CACHE ?', 'UPDATE (SELECT ALL DISTINCT SQL_CACHE ?) AS x SET a=1', 'UPDATE ((SELECT ALL DISTINCT SQL_CACHE ?)) AS x SET a=1', 'UPDATE t JOIN (SELECT ALL DISTINCT SQL_CACHE ?) AS x ON 1 SET t.a=1', 'UPDATE { OJ (SELECT ALL DISTINCT SQL_CACHE ?) AS x } SET a=1', 'UPDATE { RESTORE ({ DO ({ SLAVE (SELECT ALL DISTINCT SQL_CACHE ?) AS x }, t) }, t) } SET a=1', 'UPDATE t SET a=(SELECT ALL DISTINCT SQL_CACHE ?)', 'DELETE FROM t WHERE a=(SELECT ALL DISTINCT SQL_CACHE ?)', 'SELECT * FROM (SELECT ALL DISTINCT SQL_CACHE ?) x', 'INSERT INTO t SELECT ALL DISTINCT SQL_CACHE ?', 'CREATE TABLE z AS SELECT ALL DISTINCT SQL_CACHE ?'];
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerSql')]
    public function testUpdateRelationOptionsPrecedeLaterInputFailures(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);
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
