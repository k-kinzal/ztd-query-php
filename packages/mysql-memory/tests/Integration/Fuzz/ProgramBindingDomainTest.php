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
final class ProgramBindingDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = ['CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN ?', 'CREATE FUNCTION missing.f() RETURNS INT RETURN ?', 'CREATE PROCEDURE p() SELECT ?', 'CREATE PROCEDURE missing.p() SELECT ?', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = ?', 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1', 'CREATE PROCEDURE p() SELECT 1', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.unknown = 1', 'CREATE FUNCTION f() RETURNS INT RETURN ? + @@unknown_variable', 'CREATE FUNCTION f() RETURNS INT BEGIN RETURN ?; COMMIT; END', 'CREATE FUNCTION f() RETURNS INT BEGIN IF FALSE THEN SELECT ?; END IF; RETURN 1; END', 'CREATE FUNCTION f() RETURNS INT BEGIN DECLARE x INT DEFAULT ?; RETURN x; END', 'CREATE PROCEDURE p() BEGIN SELECT ?; SELECT @@unknown_variable; END', 'CREATE PROCEDURE p() BEGIN SELECT ?; RETURN 1; END', "CREATE FUNCTION f(a ENUM('x','x')) RETURNS INT RETURN ?", "CREATE PROCEDURE p() BEGIN PREPARE s FROM 'SELECT ?'; END", 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.unknown = ?', 'CREATE FUNCTION `10`.`2`(`あ` DECIMAL, utf8mb4_0900_ai_ci DECIMAL, `0` DECIMAL) RETURNS MEDIUMBLOB RETURN !! JSON_OBJECTAGG(0, ?)'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $queries Requests compared through text and prepared protocols
     */
    #[DataProvider('providerStatements')]
    public function testBodyBindingsPreserveBothProtocolAndHeaderConditions(string $release, array $queries): void
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
