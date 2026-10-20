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
final class ClockOperandInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'SET NAMES utf8mb4',
            'SET timestamp=1700000000',
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE jt(a INT)',
            'CREATE TABLE ju(a INT)',
            'CREATE PROCEDURE jp() IF @active THEN SELECT 1 FROM jt JOIN ju ON NOW(9); END IF',
            'CREATE PROCEDURE jw() IF @active THEN UPDATE jt JOIN ju ON NOW(9) SET jt.a=1; END IF',
            'CALL jp()',
            'CALL jw()',
            'SELECT NOW(6)',
            'CREATE PROCEDURE p6() IF @active THEN SELECT NOW(6); END IF',
            'CALL p6()',
            'CREATE TABLE t6(a DATETIME DEFAULT CURRENT_TIMESTAMP(6))',
            'PREPARE st FROM \'SELECT NOW(6)\'',
            'EXECUTE st',
            'SELECT NOW(7)',
            'CREATE PROCEDURE p7() IF @active THEN SELECT NOW(7); END IF',
            'CALL p7()',
            'CREATE TABLE t7(a DATETIME DEFAULT CURRENT_TIMESTAMP(7))',
            'PREPARE st FROM \'SELECT NOW(7)\'',
            'EXECUTE st',
            'SELECT NOW(9)',
            'CREATE PROCEDURE p9() IF @active THEN SELECT NOW(9); END IF',
            'CALL jp()',
            'CALL jw()',
            'INSERT INTO jt VALUES(1)',
            'INSERT INTO ju VALUES(2)',
            'CALL jp()',
            'CALL jw()',
            'CALL p9()',
            'CREATE TABLE t9(a DATETIME DEFAULT CURRENT_TIMESTAMP(9))',
            'PREPARE st FROM \'SELECT NOW(9)\'',
            'EXECUTE st',
            'SELECT NOW(255)',
            'CREATE PROCEDURE p255() IF @active THEN SELECT NOW(255); END IF',
            'CALL p255()',
            'CREATE TABLE t255(a DATETIME DEFAULT CURRENT_TIMESTAMP(255))',
            'PREPARE st FROM \'SELECT NOW(255)\'',
            'EXECUTE st',
            'SELECT NOW(256)',
            'CREATE PROCEDURE p256() IF @active THEN SELECT NOW(256); END IF',
            'CALL p256()',
            'CREATE TABLE t256(a DATETIME DEFAULT CURRENT_TIMESTAMP(256))',
            'PREPARE st FROM \'SELECT NOW(256)\'',
            'EXECUTE st',
            'SELECT NOW(262)',
            'CREATE PROCEDURE p262() IF @active THEN SELECT NOW(262); END IF',
            'CALL p262()',
            'CREATE TABLE t262(a DATETIME DEFAULT CURRENT_TIMESTAMP(262))',
            'PREPARE st FROM \'SELECT NOW(262)\'',
            'EXECUTE st',
            'SELECT NOW(263)',
            'CREATE PROCEDURE p263() IF @active THEN SELECT NOW(263); END IF',
            'CALL p263()',
            'CREATE TABLE t263(a DATETIME DEFAULT CURRENT_TIMESTAMP(263))',
            'PREPARE st FROM \'SELECT NOW(263)\'',
            'EXECUTE st',
            'SELECT NOW(512)',
            'CREATE PROCEDURE p512() IF @active THEN SELECT NOW(512); END IF',
            'CALL p512()',
            'CREATE TABLE t512(a DATETIME DEFAULT CURRENT_TIMESTAMP(512))',
            'PREPARE st FROM \'SELECT NOW(512)\'',
            'EXECUTE st',
            'SELECT NOW(65536)',
            'CREATE PROCEDURE p65536() IF @active THEN SELECT NOW(65536); END IF',
            'CALL p65536()',
            'CREATE TABLE t65536(a DATETIME DEFAULT CURRENT_TIMESTAMP(65536))',
            'PREPARE st FROM \'SELECT NOW(65536)\'',
            'EXECUTE st',
            'SELECT NOW(4294967296)',
            'CREATE PROCEDURE p4294967296() IF @active THEN SELECT NOW(4294967296); END IF',
            'CALL p4294967296()',
            'CREATE TABLE t4294967296(a DATETIME DEFAULT CURRENT_TIMESTAMP(4294967296))',
            'PREPARE st FROM \'SELECT NOW(4294967296)\'',
            'EXECUTE st',
            'SELECT NOW(9223372036854775808)',
            'CREATE PROCEDURE p9223372036854775808() IF @active THEN SELECT NOW(9223372036854775808); END IF',
            'CALL p9223372036854775808()',
            'CREATE TABLE t9223372036854775808(a DATETIME DEFAULT CURRENT_TIMESTAMP(9223372036854775808))',
            'PREPARE st FROM \'SELECT NOW(9223372036854775808)\'',
            'EXECUTE st',
            'SELECT NOW(18446744073709551615)',
            'CREATE PROCEDURE p18446744073709551615() IF @active THEN SELECT NOW(18446744073709551615); END IF',
            'CALL p18446744073709551615()',
            'CREATE TABLE t18446744073709551615(a DATETIME DEFAULT CURRENT_TIMESTAMP(18446744073709551615))',
            'PREPARE st FROM \'SELECT NOW(18446744073709551615)\'',
            'EXECUTE st',
            'SELECT NOW(18446744073709551616)',
            'CREATE PROCEDURE p18446744073709551616() IF @active THEN SELECT NOW(18446744073709551616); END IF',
            'CALL p18446744073709551616()',
            'CREATE TABLE t18446744073709551616(a DATETIME DEFAULT CURRENT_TIMESTAMP(18446744073709551616))',
            'PREPARE st FROM \'SELECT NOW(18446744073709551616)\'',
            'EXECUTE st',
            'SET @active=1',
            'CALL p9()',
            'CALL p256()',
            'CREATE PROCEDURE pdecl() BEGIN DECLARE a DATETIME DEFAULT NOW(9); SELECT a; END',
            'CALL pdecl()',
            'DROP TABLE IF EXISTS t',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a DATETIME DEFAULT CURRENT_TIMESTAMP(9) DEFAULT CURRENT_TIMESTAMP(0))',
            'SHOW CREATE TABLE t',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a DATETIME ON UPDATE CURRENT_TIMESTAMP(9) ON UPDATE CURRENT_TIMESTAMP(0))',
            'SHOW CREATE TABLE t',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a DATETIME DEFAULT CURRENT_TIMESTAMP(9) DEFAULT NULL)',
            'SHOW CREATE TABLE t',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a DATETIME DEFAULT CURRENT_TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(0))',
            'SHOW CREATE TABLE t',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Clock calls, stored instructions and effective column timestamp attributes
     */
    #[DataProvider('providerStatements')]
    public function testClockRolesRetainNativePreparationAndAttributeReplacement(string $release, array $statements): void
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
