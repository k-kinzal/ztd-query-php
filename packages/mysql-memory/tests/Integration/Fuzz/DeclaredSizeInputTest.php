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
final class DeclaredSizeInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'CREATE TABLE t(a DATETIME(7), b INT ZEROFILL)',
            'CREATE TABLE absent.t(a DATETIME(7), b INT ZEROFILL)',
            'CREATE PROCEDURE absent.p() BEGIN CREATE TABLE t(a DATETIME(7), b INT ZEROFILL); END',
            'CREATE DATABASE d', 'USE d',
            'CREATE TABLE t(a DATETIME(7), b INT ZEROFILL)',
            'CREATE TABLE t(a DATETIME(7) DEFAULT missing(), b INT ZEROFILL)',
            'CREATE TABLE t(a DATETIME(7), b VARCHAR(1) CHARACTER SET absent)',
            'ALTER TABLE t ADD a DATETIME(7), ADD b INT ZEROFILL',
            'ALTER TABLE absent.t ADD a DATETIME(7)',
            'CREATE TABLE t(a DATETIME(7),',
            'CREATE TABLE t(a DATETIME(7) DEFAULT',
            'ALTER TABLE t ADD a DATETIME(7),',
            'CREATE PROCEDURE p() BEGIN IF FALSE THEN CREATE TABLE u(a DATETIME(7), b INT ZEROFILL); END IF; END',
            'CREATE PROCEDURE q() BEGIN IF FALSE THEN ALTER TABLE absent ADD a DATETIME(7); END IF; END',
            'CREATE PROCEDURE r() BEGIN DECLARE a INT(256) DEFAULT !1; END',
            'CREATE PROCEDURE s(IN a DATETIME(7)) BEGIN END',
            'CREATE FUNCTION f() RETURNS DATETIME(7) DETERMINISTIC RETURN NULL',
            'CREATE TABLE t(a INT(11), b BIT(65) DEFAULT (!1), c INT(11))',
            'ALTER TABLE t ADD (a INT(11), b BIT(65)), CHANGE c d INT(256)',
            'CREATE TABLE t(a INT)',
            'CREATE TABLE IF NOT EXISTS t(a DATETIME(7))',
            'ALTER TABLE t ADD b DATETIME(7)',
            'CREATE TABLE u(a DECIMAL(66,31))',
            'CREATE TABLE u(a BIT(0))',
            'CREATE TABLE bounds(a BIT(64), b DATETIME(6), c DECIMAL(65,30))',
            'SHOW TABLES',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Declared bounds, malformed continuations and unused program branches
     */
    #[DataProvider('providerStatements')]
    public function testDeclaredBoundsRetainCompletionAndDatabasePrecedence(string $release, array $statements): void
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
