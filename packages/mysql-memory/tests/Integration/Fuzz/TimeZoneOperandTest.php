<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TimeZoneOperandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE ts(t TIMESTAMP(6) NULL, d DATETIME(6) NULL)',
            'INSERT INTO ts VALUES(NULL,NULL),(\'2024-01-01 01:02:03.123456\',\'2024-01-01 01:02:03.123456\')',
            'SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(1 AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(1 AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(1 AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TRUE AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TRUE AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TRUE AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TRUE AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(\'2024-01-01 01:02:03\' AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(\'2024-01-01 01:02:03\' AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(\'2024-01-01 01:02:03\' AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(\'2024-01-01 01:02:03\' AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(DATE \'2024-01-01\' AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(DATE \'2024-01-01\' AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(DATE \'2024-01-01\' AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(DATE \'2024-01-01\' AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIME \'01:02:03\' AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIME \'01:02:03\' AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIME \'01:02:03\' AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIME \'01:02:03\' AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(NULL AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(NULL AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(NULL AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(NULL AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIMESTAMP \'2024-01-01 01:02:03.123456\' AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIMESTAMP \'2024-01-01 01:02:03.123456\' AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIMESTAMP \'2024-01-01 01:02:03.123456\' AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(TIMESTAMP \'2024-01-01 01:02:03.123456\' AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((1) AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((1) AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((1) AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((1) AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((TIMESTAMP \'2024-01-01 01:02:03\') AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((TIMESTAMP \'2024-01-01 01:02:03\') AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((TIMESTAMP \'2024-01-01 01:02:03\') AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST((TIMESTAMP \'2024-01-01 01:02:03\') AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(t AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(t AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(t AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(t AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(d AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(d AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(d AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(d AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(MIN(t) AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(MIN(t) AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(MIN(t) AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(MIN(t) AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(IF(1,t,NULL) AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(IF(1,t,NULL) AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(IF(1,t,NULL) AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(IF(1,t,NULL) AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(CAST(NULL AS DATETIME) AT TIME ZONE \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(CAST(NULL AS DATETIME) AT TIME ZONE \'UTC\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(CAST(NULL AS DATETIME) AT TIME ZONE INTERVAL \'+00:00\' AS DATETIME(6)) AS v FROM ts',
            'SELECT CAST(CAST(NULL AS DATETIME) AT TIME ZONE \'bad\' AS DATETIME(6)) AS v FROM ts',
            'CREATE PROCEDURE p0() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p0()',
            'SET @run=1',
            'CALL p0()',
            'SET @run=0',
            'CALL p0()',
            'CREATE PROCEDURE p1() BEGIN IF @run THEN BEGIN SELECT CAST(TRUE AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p1()',
            'SET @run=1',
            'CALL p1()',
            'SET @run=0',
            'CALL p1()',
            'CREATE PROCEDURE p2() BEGIN IF @run THEN BEGIN SELECT CAST(\'2024-01-01 01:02:03\' AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p2()',
            'SET @run=1',
            'CALL p2()',
            'SET @run=0',
            'CALL p2()',
            'CREATE PROCEDURE p3() BEGIN IF @run THEN BEGIN SELECT CAST(DATE \'2024-01-01\' AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p3()',
            'SET @run=1',
            'CALL p3()',
            'SET @run=0',
            'CALL p3()',
            'CREATE PROCEDURE p4() BEGIN IF @run THEN BEGIN SELECT CAST(TIME \'01:02:03\' AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p4()',
            'SET @run=1',
            'CALL p4()',
            'SET @run=0',
            'CALL p4()',
            'CREATE PROCEDURE p5() BEGIN IF @run THEN BEGIN SELECT 1 + CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p5()',
            'SET @run=1',
            'CALL p5()',
            'SET @run=0',
            'CALL p5()',
            'CREATE PROCEDURE p6() BEGIN IF @run THEN BEGIN SELECT ABS(CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME)) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p6()',
            'SET @run=1',
            'CALL p6()',
            'SET @run=0',
            'CALL p6()',
            'CREATE PROCEDURE p7() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) = 1 AS v; END; END IF; END',
            'SET @run=0',
            'CALL p7()',
            'SET @run=1',
            'CALL p7()',
            'SET @run=0',
            'CALL p7()',
            'CREATE PROCEDURE p8() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) IS TRUE AS v; END; END IF; END',
            'SET @run=0',
            'CALL p8()',
            'SET @run=1',
            'CALL p8()',
            'SET @run=0',
            'CALL p8()',
            'CREATE PROCEDURE p9() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) IN (1) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p9()',
            'SET @run=1',
            'CALL p9()',
            'SET @run=0',
            'CALL p9()',
            'CREATE PROCEDURE p10() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) LIKE \'1\' AS v; END; END IF; END',
            'SET @run=0',
            'CALL p10()',
            'SET @run=1',
            'CALL p10()',
            'SET @run=0',
            'CALL p10()',
            'CREATE PROCEDURE p11() BEGIN IF @run THEN BEGIN SELECT (CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME)) AS v; END; END IF; END',
            'SET @run=0',
            'CALL p11()',
            'SET @run=1',
            'CALL p11()',
            'SET @run=0',
            'CALL p11()',
            'CREATE PROCEDURE p12() BEGIN IF @run THEN BEGIN IF CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) THEN SELECT 1; END IF; END; END IF; END',
            'SET @run=0',
            'CALL p12()',
            'SET @run=1',
            'CALL p12()',
            'SET @run=0',
            'CALL p12()',
            'CREATE PROCEDURE p13() BEGIN IF @run THEN BEGIN SET @x=CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME); END; END IF; END',
            'SET @run=0',
            'CALL p13()',
            'SET @run=1',
            'CALL p13()',
            'SET @run=0',
            'CALL p13()',
            'CREATE PROCEDURE p14() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME) FROM missing; END; END IF; END',
            'SET @run=0',
            'CALL p14()',
            'SET @run=1',
            'CALL p14()',
            'SET @run=0',
            'CALL p14()',
            'CREATE PROCEDURE p15() BEGIN IF @run THEN BEGIN SELECT missing, CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME); END; END IF; END',
            'SET @run=0',
            'CALL p15()',
            'SET @run=1',
            'CALL p15()',
            'SET @run=0',
            'CALL p15()',
            'CREATE PROCEDURE p16() BEGIN IF @run THEN BEGIN SELECT CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME), missing; END; END IF; END',
            'SET @run=0',
            'CALL p16()',
            'SET @run=1',
            'CALL p16()',
            'SET @run=0',
            'CALL p16()',
            'CREATE PROCEDURE p17() BEGIN IF @run THEN BEGIN SELECT 1 WHERE CAST(1 AT TIME ZONE \'+00:00\' AS DATETIME); END; END IF; END',
            'SET @run=0',
            'CALL p17()',
            'SET @run=1',
            'CALL p17()',
            'SET @run=0',
            'CALL p17()',
        ];
        $cast = "CAST(1 AT TIME ZONE '+00:00' AS DATETIME)";
        foreach (['NOT ' . $cast, '-' . $cast, $cast . ' IS NULL', $cast . ' IS NOT NULL'] as $index => $expression) {
            $name = 'wrapped' . $index;
            array_push($statements, 'SELECT ' . $expression, 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN SELECT ' . $expression . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()');
        }
        foreach (['CAST(NULL AS SIGNED)', 'CAST(NULL AS DATETIME)', 'CAST(missing AS DATETIME)', 'NULL+1', 'CURRENT_TIMESTAMP', 't + INTERVAL 0 SECOND', 'INTERVAL 0 SECOND + t'] as $index => $operand) {
            $query = 'SELECT CAST(' . $operand . " AT TIME ZONE '+00:00' AS DATETIME) AS v FROM ts";
            $name = 'fixed_type' . $index;
            array_push($statements, $query, 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN ' . $query . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()');
        }
        foreach (['CHAR', 'SIGNED ARRAY', 'CHAR(3) ARRAY', 'DATETIME ARRAY'] as $index => $target) {
            $expression = "CAST(CAST(1 AT TIME ZONE '+00:00' AS DATETIME) AS " . $target . ')';
            $name = 'cast_' . $index;
            array_push($statements, 'SELECT ' . $expression, 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN SELECT ' . $expression . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()');
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testTimeZoneOperandsPreservePreparationAndInvocationRoles(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
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
