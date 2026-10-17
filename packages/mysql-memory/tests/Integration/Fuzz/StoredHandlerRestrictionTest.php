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
final class StoredHandlerRestrictionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ["CREATE PROCEDURE `あ`.x(INOUT `1` BIT, `2` DECIMAL) HANDLER `'` READ NEXT", 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)'];
        foreach (['HANDLER t OPEN', 'HANDLER t CLOSE', 'HANDLER t READ NEXT', 'HANDLER t READ a FIRST', 'HANDLER t READ a = (1)'] as $body) {
            $statements[] = 'CREATE PROCEDURE missing.p() ' . $body;
            $statements[] = 'CREATE PROCEDURE p() BEGIN IF 0 THEN ' . $body . '; END IF; END';
            $statements[] = 'DROP PROCEDURE IF EXISTS p';
            $statements[] = 'CREATE FUNCTION f() RETURNS INT BEGIN ' . $body . '; RETURN 0; END';
            $statements[] = 'DROP FUNCTION IF EXISTS f';
            $statements[] = 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ' . $body;
            $statements[] = 'DROP TRIGGER IF EXISTS tr';
            $statements[] = 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO ' . $body;
            $statements[] = 'DROP EVENT IF EXISTS e';
            $statements[] = 'CREATE PROCEDURE p() BEGIN ' . $body . '; BOGUS; END';
        }
        foreach (['HANDLER', 'HANDLER t', 'HANDLER t READ', 'HANDLER t BOGUS', 'HANDLER t READ NEXT WHERE ABS()', 'HANDLER t READ NEXT WHERE @@unknown', 'HANDLER t READ NEXT WHERE (SELECT 1,2)'] as $body) {
            $statements[] = 'CREATE PROCEDURE p() BEGIN IF 0 THEN ' . $body . '; END IF; BOGUS; END';
        }
        $statements[] = 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; END';
        $statements[] = 'CALL p()';
        $statements[] = "SHOW SESSION STATUS LIKE 'Com_create_procedure'";
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testHandlerStatementsCannotAppearInAStoredBody(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
