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
final class ProgramBodyDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $forms = ['PREPARE s FROM @x', 'SELECT 1', 'COMMIT', 'FLUSH TABLES', str_starts_with($release, '5.') ? 'RESET SLAVE' : 'RESET REPLICA'];
            $statements = ['CREATE DATABASE d', "SET sql_mode=''", 'CREATE FUNCTION f(a INT(3)) RETURNS INT(3) BEGIN COMMIT; RETURN 1; END', "CREATE FUNCTION f(a ENUM('a','a')) RETURNS INT(3) BEGIN COMMIT; RETURN 1; END", 'CREATE FUNCTION d.f(a INT(3)) RETURNS INT(3) BEGIN COMMIT; RETURN 1; END', "CREATE FUNCTION d.f(a INT(3)) RETURNS ENUM('a','a') BEGIN SELECT BINARY 1; RETURN 1; END", "SET sql_mode='STRICT_TRANS_TABLES'", "CREATE FUNCTION f(a INT(3)) RETURNS ENUM('a','a') BEGIN END", "CREATE FUNCTION d.f(a INT(3)) RETURNS ENUM('a','a') BEGIN SELECT BINARY 1; RETURN 1; END", 'CREATE TABLE d.t(a INT)', 'CREATE USER u'];
            $grants = ['GRANT ALL ON PROCEDURE *.* TO u', 'GRANT EXECUTE ON d.t TO u', 'GRANT ALL ON d.* TO u', 'REVOKE ALL ON d.* FROM u'];
            if (!str_starts_with($release, '5.')) {
                $grants[] = 'GRANT ALL ON d.* TO u AS CURRENT_USER';
            }
            foreach (['', 'd.', 'missing.'] as $database) {
                foreach ([...$grants, ...$forms, 'BEGIN END'] as $index => $instruction) {
                    $statements[] = 'CREATE FUNCTION ' . $database . 'h' . $index . '() RETURNS INT DETERMINISTIC BEGIN ' . $instruction . '; END';
                }
            }
            $statements[] = 'USE d';
            foreach ($grants as $index => $grant) {
                array_push($statements, 'CREATE FUNCTION f' . $index . '() RETURNS INT DETERMINISTIC BEGIN ' . $grant . '; RETURN 1; END', 'SELECT f' . $index . '()', 'CREATE FUNCTION s' . $index . '() RETURNS INT DETERMINISTIC BEGIN IF FALSE THEN ' . $grant . '; END IF; RETURN 1; END', 'SELECT s' . $index . '()', 'CREATE TRIGGER t' . $index . ' BEFORE INSERT ON t FOR EACH ROW ' . $grant, 'INSERT INTO t VALUES(1)', 'DROP TRIGGER IF EXISTS t' . $index);
            }
            foreach ($forms as $first => $left) {
                foreach ($forms as $second => $right) {
                    if ($first !== $second) {
                        $statements[] = 'CREATE FUNCTION f' . $first . $second . '() RETURNS INT DETERMINISTIC BEGIN ' . $left . '; ' . $right . '; RETURN 1; END';
                    }
                }
            }
            foreach (['LOCK TABLES t READ', 'UNLOCK TABLES', 'USE d', 'RETURN 1'] as $index => $instruction) {
                array_push($statements, 'CREATE PROCEDURE p' . $index . '() BEGIN IF FALSE THEN ' . $instruction . '; END IF; END', 'CREATE EVENT e' . $index . ' ON SCHEDULE EVERY 1 DAY DO BEGIN IF FALSE THEN ' . $instruction . '; END IF; END', 'ALTER EVENT e' . $index . ' DO ' . $instruction);
            }
            array_push($statements, 'CREATE FUNCTION existing() RETURNS INT DETERMINISTIC RETURN 1', 'CREATE TRIGGER existing BEFORE INSERT ON t FOR EACH ROW SET @a = 1');
            if (!str_starts_with($release, '5.')) {
                foreach (['GRANT ALL ON d.* TO u', 'SELECT 1', 'BEGIN END'] as $body) {
                    array_push($statements, 'CREATE FUNCTION IF NOT EXISTS existing() RETURNS INT ' . $body, 'CREATE FUNCTION IF NOT EXISTS missing() RETURNS INT ' . $body, 'CREATE TRIGGER IF NOT EXISTS existing BEFORE INSERT ON t FOR EACH ROW ' . $body);
                }
            }
            foreach (['STRICT_TRANS_TABLES', ''] as $mode) {
                array_push($statements, "SET sql_mode='" . $mode . "'", "CREATE FUNCTION g() RETURNS SET('a','a') DO 1", "CREATE FUNCTION g(a ENUM('a','a')) RETURNS INT BEGIN COMMIT; RETURN 1; END", "CREATE PROCEDURE p(a ENUM('a','a')) LOCK TABLES t READ");
            }
            yield $release => [$release, [...$statements, ...self::providerTypeStatements()]];
        }
    }

    /**
     * @return list<string> Header notes and later grammar warnings under both SQL modes
     */
    public static function providerTypeStatements(): array
    {
        $statements = [];
        foreach (['', 'STRICT_TRANS_TABLES'] as $mode) {
            $statements[] = "SET sql_mode='" . $mode . "'";
            foreach (["a ENUM('a','a'),b INT(3)", "a INT(3),b ENUM('a','a')", "a ENUM('a','a'),b DOUBLE(3,1)", "a SET('a','a')"] as $index => $args) {
                foreach (['BEGIN COMMIT; RETURN 1; END', 'BEGIN SELECT BINARY 1; RETURN 1; END', 'BEGIN END'] as $branch => $body) {
                    $statements[] = 'CREATE FUNCTION types' . $index . $branch . '(' . $args . ') RETURNS INT(3) ' . $body;
                }
            }
        }
        return $statements;
    }

    /**
     * @param list<string> $statements Definition kinds, unreachable branches, existing objects and header precedence
     */
    #[DataProvider('providerStatements')]
    public function testDeclarationKindsPreserveNativeBodyAndHeaderPriority(string $release, array $statements): void
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
