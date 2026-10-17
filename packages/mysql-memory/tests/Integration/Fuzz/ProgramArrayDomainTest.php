<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_sql_exception;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ProgramArrayDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = ['CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS JSON ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS TIME(7) ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY), BINARY 1', 'CREATE PROCEDURE p() SELECT BINARY 1, CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() BEGIN SELECT @@bad; SELECT CAST(1 AS SIGNED ARRAY); END', 'CREATE PROCEDURE p() BEGIN SELECT CAST(1 AS SIGNED ARRAY); SELECT @@bad; END', 'CREATE PROCEDURE p() BEGIN DECLARE v INT DEFAULT CAST(1 AS SIGNED ARRAY); SELECT v; END', 'CREATE FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY)', 'CREATE FUNCTION f() RETURNS INT BEGIN COMMIT; RETURN CAST(1 AS SIGNED ARRAY); END', 'CREATE PROCEDURE p() RETURN CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() BEGIN RETURN 1; SELECT CAST(1 AS SIGNED ARRAY); END', 'CREATE PROCEDURE p() BEGIN SELECT CAST(1 AS SIGNED ARRAY); RETURN 1; END', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @v=CAST(1 AS SIGNED ARRAY)', 'CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1', 'CREATE PROCEDURE p() CREATE TABLE t(j JSON, KEY((CAST(j AS UNSIGNED ARRAY))))', 'CREATE PROCEDURE p(a ENUM(\'a\',\'a\')) SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE missing.p() SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY),', 'CREATE PROCEDURE p() BEGIN SELECT CAST(1 AS SIGNED ARRAY); SELECT ; END', 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY) + CAST(1 AS TIME(7))', 'CREATE PROCEDURE p() SELECT CAST(1 AS TIME(7)) + CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY) + CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() BEGIN IF 0 THEN SELECT CAST(1 AS SIGNED ARRAY); END IF; END', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT CAST(1 AS SIGNED ARRAY); END', 'CREATE PROCEDURE p() SELECT CAST(CAST(1 AS TIME(7)) AS SIGNED ARRAY)', 'CREATE PROCEDURE p() BEGIN USE d; SELECT CAST(1 AS SIGNED ARRAY); END', 'ALTER EVENT e DO SELECT CAST(1 AS SIGNED ARRAY)', 'ALTER EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED)', 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() SELECT \'CAST(1 AS SIGNED ARRAY)\'', 'DROP PROCEDURE IF EXISTS p'];
        array_push($queries, 'DROP EVENT IF EXISTS e', 'DROP TRIGGER IF EXISTS tr', 'DROP FUNCTION IF EXISTS f', "ALTER PROCEDURE p COMMENT 'x'", "ALTER FUNCTION f COMMENT 'x'");
        array_push($queries, 'CREATE PROCEDURE p() CREATE FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() CREATE PROCEDURE q() SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO SELECT CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() BEGIN CREATE FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY); END', 'CREATE PROCEDURE p() CREATE DEFINER=\'u\'@\'localhost\' FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY)', 'CREATE PROCEDURE p() CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x=CAST(1 AS SIGNED ARRAY)', 'CREATE FUNCTION f() RETURNS INT BEGIN CREATE TABLE t2(a INT); RETURN CAST(1 AS SIGNED ARRAY); END');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $queries Requests compared through text and prepared protocols
     */
    #[DataProvider('providerStatements')]
    public function testBodyArraysPreserveInputPhaseAndHeaderConditions(string $release, array $queries): void
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
                $native->query("SET sql_mode=''");
                $memory->query("SET sql_mode=''");
                $observed = array_map(static function (array $setup) use ($native, $memory, $queries): array {
                    foreach ($setup as $sql) {
                        $native->query($sql);
                        $memory->query($sql);
                    }
                    return array_merge(...array_map(static function (string $sql) use ($native, $memory): array {
                        return array_map(static function (bool $prepared) use ($native, $memory, $sql): array {
                            $observe = static function (mysqli $connection) use ($prepared, $sql): array {
                                if (!$prepared) {
                                    return (new Native())->observe($connection, $sql);
                                }
                                try {
                                    $statement = $connection->prepare($sql);
                                    self::assertNotFalse($statement);
                                    $outcome = ['parameters' => $statement->param_count];
                                    $statement->close();
                                } catch (mysqli_sql_exception $error) {
                                    $outcome = ['error' => [$error->getCode(), $error->getSqlState(), $error->getMessage()]];
                                }
                                return $outcome + (new Native())->conditions($connection);
                            };
                            return [serialize($observe($native)), serialize($observe($memory))];
                        }, [false, true]);
                    }, $queries));
                }, [[], ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)']]);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                $memory->close();
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
