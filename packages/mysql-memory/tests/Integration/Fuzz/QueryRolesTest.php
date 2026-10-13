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
final class QueryRolesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testQueryPositionsMatchTheNativeGrammarAndExecution(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $native->exec("SET SESSION sql_mode=''");
                $memory->exec("SET SESSION sql_mode=''");
                $native->exec('CREATE DATABASE query_roles');
                $memory->exec('CREATE DATABASE query_roles');
                $native->exec('USE query_roles');
                $memory->exec('USE query_roles');
                $native->exec('CREATE TABLE t (a INT)');
                $memory->exec('CREATE TABLE t (a INT)');
                $observer = new Observer();
                $statements = ['SELECT 1 WHERE 0', '(SELECT 1 WHERE 0)', 'SELECT (SELECT 1 WHERE 0)', 'SELECT (SELECT 1 WHERE 1)', 'SELECT 1 UNION SELECT 2 WHERE 0', 'INSERT INTO t SELECT 1 WHERE 0', 'SELECT (SELECT 1,2 WHERE 0) <=> (SELECT 1,2 WHERE 0), (NULL,NULL) <=> (SELECT 1,2 WHERE 0)', 'SELECT * PROCEDURE ANALYSE()', 'INSERT INTO t SELECT * PROCEDURE ANALYSE()', 'CREATE VIEW v AS SELECT a FROM t', 'DROP TABLE t', 'CREATE TABLE t (a VARCHAR(12))', "INSERT INTO t VALUES ('value')", 'SELECT * FROM v', 'DROP VIEW v', 'CREATE VIEW v(x) AS SELECT * FROM t', 'DROP TABLE t', 'CREATE TABLE t (b INT, a VARCHAR(12))', "INSERT INTO t VALUES (1, 'kept')", 'SELECT * FROM v', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; OPEN c; FETCH c INTO x; CLOSE c; SELECT x; END', 'CALL p()', 'DROP PROCEDURE p'];
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
