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
final class ExternalRoutineLanguageTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ["SET sql_mode=''"];
        $index = 0;
        foreach ([[], ['CREATE DATABASE d', 'USE d']] as $setup) {
            array_push($statements, ...$setup);
            $statements[] = "CREATE FUNCTION A1B2C() RETURNS DECIMAL AS 'utf8mb4_0900_ai_ci'";
            foreach (['FUNCTION', 'PROCEDURE'] as $kind) {
                foreach (['', 'LANGUAGE SQL', 'LANGUAGE `SQL`', 'LANGUAGE `sql`', 'LANGUAGE JAVASCRIPT', 'LANGUAGE PYTHON', 'LANGUAGE SQL LANGUAGE JAVASCRIPT', 'LANGUAGE JAVASCRIPT LANGUAGE SQL', 'LANGUAGE JAVASCRIPT LANGUAGE `sql`'] as $language) {
                    foreach ([$kind === 'FUNCTION' ? 'RETURN 1' : 'SELECT 1', "AS 'return 1;'", 'AS $$ return 1; $$'] as $body) {
                        $name = 'r' . ++$index;
                        $statements[] = 'CREATE ' . $kind . ' ' . $name . '() ' . ($kind === 'FUNCTION' ? 'RETURNS INT DETERMINISTIC ' : '') . $language . ' ' . $body;
                        $statements[] = ($kind === 'FUNCTION' ? 'SELECT ' : 'CALL ') . $name . '()';
                    }
                }
            }
        }
        foreach (['SELECT 1', 'BEGIN END', 'BEGIN COMMIT; RETURN 1; END', 'RETURN 1 /* trailing */'] as $body) {
            $statements[] = 'CREATE FUNCTION bad() RETURNS INT LANGUAGE JAVASCRIPT ' . $body;
        }
        foreach (['RETURN 1', 'USE d'] as $body) {
            $statements[] = 'CREATE PROCEDURE bad() LANGUAGE JAVASCRIPT ' . $body;
        }
        array_push($statements, "CREATE FUNCTION f2() RETURNS INT AS 'body' /* trailing */", "CREATE FUNCTION f() RETURNS INT DETERMINISTIC LANGUAGE JAVASCRIPT AS 'return 1;'", 'SELECT f()', "CREATE PROCEDURE p() LANGUAGE JAVASCRIPT AS 'return 1;'", 'CALL p()', "CREATE FUNCTION f() RETURNS INT DETERMINISTIC LANGUAGE JAVASCRIPT AS 'return 1;'", "CREATE FUNCTION IF NOT EXISTS f() RETURNS INT DETERMINISTIC LANGUAGE JAVASCRIPT AS 'return 1;'", "CREATE FUNCTION u() RETURNS INT LANGUAGE PYTHON AS 'body'", "CREATE FUNCTION missing.f() RETURNS INT LANGUAGE PYTHON AS 'body'", "CREATE FUNCTION missing.f() RETURNS INT DETERMINISTIC LANGUAGE PYTHON AS 'body'");
        foreach (['8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Original contradictions, ordered characteristics and invocation histories
     */
    #[DataProvider('providerStatements')]
    public function testLanguageAndBodyRelationshipPreservesNativeConditions(string $release, array $statements): void
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
