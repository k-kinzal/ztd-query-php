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
final class RoutineCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'SET GLOBAL log_bin_trust_function_creators=1'];
        foreach (['INT', 'SERIAL', 'VARCHAR(3)', 'VARCHAR(3) BINARY', 'VARCHAR(3) CHARACTER SET utf8mb4', 'NCHAR(3)', 'CHAR(3) UNICODE', 'VARBINARY(3)', 'BLOB', "ENUM('x')", "ENUM('x') CHARACTER SET utf8mb4"] as $type) {
            foreach (['utf8mb4_bin', "'binary'", 'ucs2_bin'] as $collation) {
                $statements = [...$statements, "CREATE PROCEDURE p(x $type COLLATE $collation) BEGIN END", 'DROP PROCEDURE IF EXISTS p', "CREATE FUNCTION f() RETURNS $type COLLATE $collation RETURN NULL", 'DROP FUNCTION IF EXISTS f', "CREATE PROCEDURE p() BEGIN DECLARE x $type COLLATE $collation; END", 'DROP PROCEDURE IF EXISTS p'];
            }
        }
        $statements = [...$statements,
            'CREATE PROCEDURE p(x INT COLLATE utf8mb4_bin) trailing',
            'CREATE PROCEDURE p(x INT COLLATE utf8mb4_bin, y YEAR(4)) BEGIN END',
            "CREATE PROCEDURE p(x INT COLLATE utf8mb4_bin) 'unfinished",
            'CREATE FUNCTION f(x INT COLLATE utf8mb4_bin) RETURNS YEAR(4) LANGUAGE javascript AS $$code$$',
            'CREATE FUNCTION f() RETURNS INT COLLATE utf8mb4_bin RETURN BINARY 1',
            "CREATE FUNCTION f() RETURNS INT COLLATE utf8mb4_bin RETURN DATE 'bad'",
            "CREATE FUNCTION f() RETURNS INT COLLATE utf8mb4_bin 'unfinished",
            "CREATE PROCEDURE p() BEGIN DECLARE x INT COLLATE utf8mb4_bin DEFAULT DATE 'bad'; END",
            'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; DECLARE y INT COLLATE utf8mb4_bin; END',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN BEGIN DECLARE x INT COLLATE utf8mb4_bin; END; END IF; END',
            'CREATE PROCEDURE p(x VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin) SELECT COLLATION(x)',
            "CALL p('x')", 'DROP PROCEDURE IF EXISTS p',
            'CREATE PROCEDURE p() BEGIN DECLARE x VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin; SELECT COLLATION(x); END',
            'CALL p()', 'DROP PROCEDURE IF EXISTS p',
            'CREATE PROCEDURE p(x CHAR(3) UNICODE COLLATE ucs2_bin) SELECT COLLATION(x)',
            "CALL p('x')", "CALL p('x')", 'FLUSH TABLES', "CALL p('x')",
            "ALTER PROCEDURE p COMMENT 'x'", "CALL p('x')", "CALL p('x')",
            'DROP PROCEDURE p',
            "CREATE PROCEDURE p(x CHAR(3) UNICODE COLLATE ucs2_bin) 'unfinished",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Declaration domains and competing input conditions
     */
    #[DataProvider('providerInputs')]
    public function testRoutineDeclarationsRetainTheNativeCollationDomainAndRefusalOrder(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            try {
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $memory->close();
            }
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
