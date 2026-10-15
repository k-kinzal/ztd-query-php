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
final class TableIdentityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'ALTER TABLE `` ADD a INT',
            'OPTIMIZE TABLE ``.t',
            'CREATE PROCEDURE p() ALTER TABLE `` ADD a INT',
            'CREATE DATABASE table_domain',
            'USE table_domain',
            'CREATE TABLE t(a INT)',
            'INSERT INTO t VALUES(1)',
            'CREATE TABLE ``(a INT)',
            'CREATE TABLE ``.t(a INT)',
            'CREATE VIEW `` AS SELECT 1 AS a',
            'SELECT a FROM ``.t',
            'SELECT * FROM ``',
            'DROP TABLE IF EXISTS ``',
            'SELECT ``.a FROM t AS ``',
            'SELECT ``.a FROM (SELECT 1 AS a) AS ``',
            'WITH `` AS (SELECT 1 AS a) SELECT 1',
            'WITH `` AS (SELECT 1 AS a) SELECT * FROM ``',
            'DELETE `` FROM t AS ``',
            'INSERT INTO t VALUES(2)',
            'DELETE FROM `` USING t AS ``',
            'HANDLER t OPEN AS ``',
            'HANDLER `` READ FIRST',
            'HANDLER `` CLOSE',
            'HANDLER `` READ FIRST WHERE CONVERT(1 USING wrong)',
            'SHOW OPEN TABLES FROM ``',
            'SELECT BINARY 1 FROM ``',
            'SELECT ? FROM ``',
            'SELECT DATE \'invalid\' FROM ``',
            'SELECT CONVERT(1 USING wrong) FROM ``',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN ALTER TABLE `` ADD a INT; END IF; END',
            'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; ALTER TABLE `` ADD a INT; END',
            "PREPARE p FROM 'ALTER TABLE `` ADD a INT'",
            "CREATE PROCEDURE p() SELECT DATE 'invalid' FROM ``",
            "CREATE PROCEDURE p() SELECT * FROM `` WHERE DATE 'invalid'",
            "CREATE PROCEDURE p() BEGIN SELECT DATE 'invalid' FROM ``; END",
            'CREATE PROCEDURE p() BEGIN ALTER TABLE `` ADD a INT;',
            'ALTER TABLE `` ADD',
            'ALTER TABLE `` ADD a INT trailing',
            "ALTER TABLE `` ADD a INT 'unclosed",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Table identities, valid aliases and preceding input conditions
     */
    #[DataProvider('providerReleases')]
    public function testTableIdentitiesRefuseEmptyNamesWhilePreservingAliases(string $release, array $statements): void
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
