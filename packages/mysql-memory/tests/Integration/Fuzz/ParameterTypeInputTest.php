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
final class ParameterTypeInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'SET NAMES utf8mb4',
            'CREATE FUNCTION RESTART .FOLLOWING ( CACHE TIMESTAMP ( 00000000007459 ) , _sf6gNh8H0E2oCZ6kpyCLMLyRHlV6qpbB1nAtBYqHe NCHAR ) RETURNS LONG VARCHAR COLLATE _sqlfaker_identifier DETERMINISTIC NOT DETERMINISTIC LANGUAGE SQL ITERATE _sfgGCO',
            'CREATE PROCEDURE p(a TIMESTAMP(9), b NCHAR) SELECT 1',
            'CREATE DATABASE d',
            'USE d',
            'CREATE PROCEDURE p1(a TIMESTAMP(9), b NCHAR) SELECT 1',
            'CREATE PROCEDURE p2(a TIME(9) COLLATE missing_collation) SELECT 1',
            'CREATE PROCEDURE p3(a INT, b TIME(9)) SELECT 1',
            'CREATE FUNCTION f1() RETURNS TIME(9) RETURN 1',
            'CREATE PROCEDURE p4() BEGIN DECLARE v TIME(9); END',
            'CREATE PROCEDURE p5() IF 0 THEN CREATE TABLE t(a TIME(9)); END IF',
            'CREATE TABLE t(a TIME(9), b NCHAR)',
            'CREATE PROCEDURE p6(a DECIMAL(66), b NCHAR) SELECT 1',
            'CREATE PROCEDURE p7(a CHAR(256), b NCHAR) SELECT 1',
            'CREATE PROCEDURE p8(a INT(256), b NCHAR) SELECT 1',
            'CREATE PROCEDURE p9(a BIT(65), b NCHAR) SELECT 1',
            'CREATE PROCEDURE p10(a TIME(9), a INT) SELECT 1',
            'CREATE PROCEDURE p11(a TIME(9), b INT) malformed',
            'CREATE PROCEDURE p12(a INT, a TIME(9)) SELECT 1',
            'CREATE FUNCTION f2(a TIMESTAMP(9)) RETURNS VARCHAR(3) COLLATE missing_collation RETURN 1',
            'CREATE FUNCTION f3(a INT) RETURNS TIME(9) ITERATE missing_label',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Type declarations before later parameters, warnings, collation names and body conditions
     */
    #[DataProvider('providerStatements')]
    public function testParameterTypeRefusalsKeepTheCompletedInputOrder(string $release, array $statements): void
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
