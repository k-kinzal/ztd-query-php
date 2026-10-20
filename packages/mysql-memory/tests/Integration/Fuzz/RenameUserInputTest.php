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
final class RenameUserInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d',
            'RENAME USER a TO a', 'RENAME USER CURRENT_USER TO CURRENT_USER',
            'CREATE USER a', 'GRANT SELECT ON d.* TO a', 'RENAME USER a TO a',
            'RENAME USER a TO b, a TO c', 'SHOW GRANTS FOR a', 'SHOW GRANTS FOR b', 'SHOW GRANTS FOR c',
            'DROP USER a, b, c', 'CREATE USER a', 'RENAME USER a TO b, c TO b',
            'SHOW GRANTS FOR a', 'SHOW GRANTS FOR b', 'SHOW GRANTS FOR c', 'DROP USER a, b, c',
            "PREPARE st FROM 'RENAME USER a TO a'", 'EXECUTE st', 'CREATE USER a', 'EXECUTE st', 'DROP USER a', 'EXECUTE st',
            "PREPARE st FROM 'RENAME USER a TO b, a TO c'", 'CREATE USER a', 'EXECUTE st', 'SHOW GRANTS FOR a', 'SHOW GRANTS FOR b',
            'DROP USER a, b, c',
            'CREATE PROCEDURE p() IF @active THEN RENAME USER a TO b, a TO c; END IF',
            'SET @active=0', 'CALL p()', 'SET @active=1', 'CALL p()', 'CREATE USER a', 'CALL p()',
            'SHOW GRANTS FOR a', 'SHOW GRANTS FOR b', 'SHOW GRANTS FOR c',
            'CREATE TABLE t(a INT)', 'START TRANSACTION', 'INSERT INTO t VALUES(1)', 'CALL p()', 'ROLLBACK', 'SELECT a FROM t',
            'CREATE FUNCTION f() RETURNS INT BEGIN RENAME USER a TO a; RETURN 1; END',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RENAME USER a TO a',
            'DROP USER a, b, c', 'SET @active=0', 'CALL p()', 'DROP DATABASE d'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete renamings across account, preparation, branch and transaction changes
     */
    #[DataProvider('providerStatements')]
    public function testOrderedRenamesRetainPreparationAndPartialInvocationEffects(string $release, array $statements): void
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
