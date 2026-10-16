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
final class ProgramConditionHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d','USE d','CREATE TABLE t(a INT)','CREATE PROCEDURE noop() BEGIN END','CREATE PROCEDURE fails() SELECT (1,2)',"CREATE PROCEDURE warns() SELECT 'new'+0",'CREATE PROCEDURE handled() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; SELECT (1,2); END',"CREATE PROCEDURE signalled() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' BEGIN END; SIGNAL SQLSTATE '45000'; END",'CREATE PROCEDURE reads_table() SELECT * FROM t',"CREATE PROCEDURE warn_then_read() BEGIN SELECT 'new'+0; SELECT * FROM t; END",'CREATE PROCEDURE nested() CALL fails()','CREATE PROCEDURE nested_noop() CALL noop()',"CREATE FUNCTION warns_f() RETURNS INT DETERMINISTIC RETURN 'inner'+0"];
        foreach (['CALL noop()','CALL fails()','CALL warns()','CALL handled()','CALL signalled()','CALL reads_table()','CALL warn_then_read()','CALL nested()','CALL nested_noop()','SELECT warns_f()',"SELECT 'current'+0, warns_f()"] as $call) {
            foreach (["SELECT 'old'+0",'SELECT (1,2)'] as $prior) {
                array_push($statements, 'SELECT * FROM t', $prior, $call);
            }
        }

        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Stored SQL conditions, handlers, table reads and nested invocations following stale warnings or errors
     */
    #[DataProvider('providerStatements')]
    public function testProgramConditionsReplaceStaleHistory(string $release, array $statements): void
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
