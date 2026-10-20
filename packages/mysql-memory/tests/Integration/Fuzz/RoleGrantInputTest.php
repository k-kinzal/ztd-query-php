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
final class RoleGrantInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d',
            "GRANT '' TO u", "GRANT r TO ''", 'GRANT r TO r', "GRANT r@'a' TO r@'A'",
            'CREATE USER u', "GRANT '' TO u", 'CREATE ROLE r', "CREATE USER r@'a'", 'GRANT r TO r', "GRANT r@'a' TO r@'A'",
            "CREATE USER ''", "GRANT r, '' TO u", 'SHOW GRANTS FOR u',
            "PREPARE st FROM 'GRANT r TO r'", 'EXECUTE st', 'DROP ROLE r', 'EXECUTE st', 'CREATE ROLE r', 'EXECUTE st',
            "CREATE PROCEDURE p() IF @active THEN GRANT r, '' TO u WITH ADMIN OPTION; END IF",
            'SET @active=0', 'CALL p()', 'SET @active=1', 'CALL p()', 'SHOW GRANTS FOR u',
            'CREATE TABLE t(a INT)', 'START TRANSACTION', 'INSERT INTO t VALUES(1)', 'CALL p()', 'ROLLBACK', 'SELECT a FROM t',
            'CREATE FUNCTION f() RETURNS INT BEGIN GRANT r TO r; RETURN 1; END',
            'DROP USER u', 'CALL p()', 'CREATE USER u', 'CALL p()', 'SET @active=0', 'CALL p()', 'DROP DATABASE d'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete role grants before and after account and transaction changes
     */
    #[DataProvider('providerStatements')]
    public function testRoleGrantsRetainAccountLookupAndInvocationConditions(string $release, array $statements): void
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
