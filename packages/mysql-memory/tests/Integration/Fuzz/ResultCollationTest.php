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
final class ResultCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a VARCHAR(3))', "INSERT INTO t VALUES('abc')", 'CREATE VIEW v AS SELECT a FROM t', 'CREATE PROCEDURE p() BEGIN SELECT a FROM t; SET NAMES utf8mb4 COLLATE utf8mb4_bin; SELECT a FROM t; END', 'CREATE USER u', 'GRANT SELECT ON d.* TO u'];
        foreach (['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', "SET collation_connection='utf8mb4_bin'", "SET character_set_results='utf8mb4'", 'SET NAMES utf8mb4 COLLATE utf8mb4_bin', 'SET character_set_results=NULL', 'SET CHARACTER SET utf8mb4'] as $change) {
            $statements = [...$statements, $change, "SELECT 'a', 1, X'61'", 'SHOW CREATE VIEW v', 'SHOW GRANTS FOR u', 'CACHE INDEX t IN DEFAULT', 'CHECKSUM TABLE t QUICK', 'HANDLER t OPEN', 'HANDLER t READ FIRST', 'HANDLER t CLOSE', 'SELECT missing', 'SHOW WARNINGS'];
        }
        $statements[] = 'CALL p()';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Result encoding histories, including multiple routine result sets
     */
    #[DataProvider('providerHistories')]
    public function testSetNamesRetainsTheSelectedResultCollationAcrossStatements(string $release, array $statements): void
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
