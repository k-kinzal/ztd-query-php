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
final class RoleCreationInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d',
            "CREATE ROLE ''", "CREATE ROLE IF NOT EXISTS ''", "CREATE ROLE r, ''@'あ'",
            'SHOW GRANTS FOR r', "PREPARE st FROM \"CREATE ROLE r, ''\"", 'EXECUTE st',
            "CREATE PROCEDURE p() IF @active THEN CREATE ROLE r, ''@'あ'; END IF",
            'SET @active=0', 'CALL p()', 'SET @active=1', 'CALL p()', 'SHOW GRANTS FOR r',
            'CREATE TABLE t(a INT)', 'START TRANSACTION', 'INSERT INTO t VALUES(1)',
            'CALL p()', 'ROLLBACK', 'SELECT a FROM t',
            "CREATE FUNCTION f() RETURNS INT BEGIN CREATE ROLE ''; RETURN 1; END",
            'CREATE ROLE r', 'CALL p()', 'DROP ROLE r', 'CALL p()',
            "CREATE USER ''", "CREATE ROLE ''", "CREATE ROLE IF NOT EXISTS ''",
            "CREATE ROLE IF NOT EXISTS r, ''", "DROP ROLE ''", "CREATE ROLE IF NOT EXISTS r, ''",
            'SET @active=0', 'CALL p()', 'DROP ROLE IF EXISTS r',
            'CREATE ROLE r, r', "CREATE ROLE r, r@'%'", 'CREATE ROLE r@Example, r@example',
            "CREATE ROLE r, 'r '", 'CREATE ROLE IF NOT EXISTS r, r', 'SHOW GRANTS FOR r',
            'DROP ROLE r', 'CREATE ROLE r, R', 'DROP ROLE r, R',
            'CREATE PROCEDURE duplicates() IF @active THEN CREATE ROLE r, r; END IF',
            'CALL duplicates()', 'SET @active=1', 'CALL duplicates()', 'SHOW GRANTS FOR r',
            'CREATE ROLE r', 'CALL duplicates()', 'DROP ROLE r', 'CALL duplicates()',
            "PREPARE duplicated FROM 'CREATE ROLE r, r'", 'EXECUTE duplicated', 'DROP DATABASE d'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete role definitions before and after account and transaction changes
     */
    #[DataProvider('providerStatements')]
    public function testRoleCreationKeepsAnonymousAccountConditionsAtTheObservedPreparationBoundary(string $release, array $statements): void
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
