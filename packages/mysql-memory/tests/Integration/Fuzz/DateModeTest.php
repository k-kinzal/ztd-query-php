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
final class DateModeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerModes(): iterable
    {
        $statements = [
            'SET NAMES utf8mb4', 'CREATE DATABASE d',
            "SELECT DATE 'bad', BINARY 1",
            "SELECT BINARY 1, DATE 'bad'",
            "SELECT DATE 'bad' TRAILING GARBAGE",
            "SELECT DATE 'bad', ?",
            "SHOW PARSE_TREE SELECT DATE 'bad'",
            "SIGNAL missing SET MESSAGE_TEXT = DATE 'bad'",
            "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = DATE 'bad'",
            "SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = @@absent, MESSAGE_TEXT = DATE 'bad'",
            "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = DATE 'bad'",
            "RESIGNAL SET MESSAGE_TEXT = DATE 'bad'",
            "CREATE PROCEDURE d.p() SELECT DATE 'bad'",
            "CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c SET MESSAGE_TEXT = DATE 'bad'; END",
            "CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SIGNAL c SET MESSAGE_TEXT = DATE 'bad'; END; END",
            "CREATE PROCEDURE d.p() BEGIN SIGNAL missing SET MESSAGE_TEXT = DATE 'bad'; END",
            "CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; SELECT DATE 'bad'; END",
            "CREATE PROCEDURE d.p() BEGIN SELECT BINARY 1; SELECT DATE 'bad'; SELECT BINARY 2; END",
            "CREATE PROCEDURE d.p() BEGIN DECLARE x DATE DEFAULT '2024-01-01'; SELECT DATE 'bad'; END",
        ];
        foreach (['', 'ALLOW_INVALID_DATES'] as $mode) {
            $statements[] = "SET sql_mode='" . $mode . "'";
            foreach (["DATE '2024-02-31'", "DATE '2023-02-29'", "DATE '2024-04-31'", "DATE '2024-13-01'", "DATE '2024-02-32'", "DATE '0000-00-00'", "DATE '2024-00-01'", "TIMESTAMP '2024-02-31 01:02:03'", "TIMESTAMP '2024-02-31 01:02:03.9999999'", "TIMESTAMP '2024-02-31 23:59:59.9999999'"] as $literal) {
                array_push($statements, 'SELECT ' . $literal, 'SELECT NAME_CONST(' . $literal . ', 1)', 'CREATE PROCEDURE d.p() SELECT ' . $literal, 'CALL d.p()', 'DROP PROCEDURE IF EXISTS d.p');
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The same literal inputs under strict and permissive calendar settings
     */
    #[DataProvider('providerModes')]
    public function testDateModesMatchNative(string $release, array $statements): void
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
