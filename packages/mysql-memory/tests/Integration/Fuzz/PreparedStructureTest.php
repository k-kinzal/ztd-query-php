<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PreparedStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSqlPreparationPreservesMeaningAcrossSessionChanges(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql = [
                    'SET NAMES utf8mb4', "SET sql_mode=''", 'CREATE DATABASE a', 'CREATE DATABASE b', 'USE a',
                    'CREATE TABLE `猫` (`猫` INT)', 'INSERT INTO `猫` VALUES (7)',
                    "PREPARE s FROM 'SELECT `猫`+1, `猫`, DATABASE(), CHARSET(''猫''), ''猫'', @@character_set_client FROM `猫`'",
                    'EXECUTE s', 'SET NAMES latin1', 'EXECUTE s', 'USE b', 'EXECUTE s', 'SET NAMES utf8mb4', 'EXECUTE s',
                    'USE a', 'PREPARE q FROM \'SELECT "猫", 1||2, DATABASE()\'',
                    "SET sql_mode='ANSI_QUOTES,PIPES_AS_CONCAT'", 'USE b', 'EXECUTE q',
                    'ALTER TABLE a.`猫` ADD other INT DEFAULT 9', 'EXECUTE s',
                    'DROP TABLE a.`猫`', 'EXECUTE s', 'CREATE TABLE a.`猫` (`猫` INT)', 'INSERT INTO a.`猫` VALUES (11)', 'EXECUTE s',
                    'SELECT DATABASE(), @@character_set_client, @@sql_mode',
                    "PREPARE u FROM 'USE a'", 'EXECUTE u',
                    "SET sql_mode=''", "PREPARE c FROM 'SELECT CHARSET(''x''), CAST(''0000-00-00'' AS DATE), @@sql_mode'",
                    "SET sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE'", 'EXECUTE c',
                    'USE a', "SET sql_mode=''", 'PREPARE r FROM \'SELECT *, "猫", 1||2 FROM `猫`\'',
                    "SET sql_mode='ANSI_QUOTES,PIPES_AS_CONCAT'", 'USE b', 'ALTER TABLE a.`猫` ADD next INT DEFAULT 5', 'EXECUTE r',
                    'ALTER TABLE a.`猫` DROP next', 'SET NAMES latin1', 'EXECUTE r', 'SET NAMES utf8mb4', 'EXECUTE r',
                    'CREATE TEMPORARY TABLE tmp (a INT)', 'INSERT INTO tmp VALUES (4)', "SET sql_mode=''",
                    'PREPARE temp FROM \'SELECT a, "a" FROM tmp\'', "SET sql_mode='ANSI_QUOTES'", 'EXECUTE temp',
                    'ALTER TABLE tmp ADD c INT', 'EXECUTE temp',
                ];
                $observer = new Observer();
                $expected = array_map(static fn (string $query): string => serialize($observer->observe($native, $query, true)), $sql);
                $actual = array_map(static fn (string $query): string => serialize($observer->observe($memory, $query, true)), $sql);
                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    #[DataProvider('providerReleases')]
    public function testBinaryPreparationPreservesNamesAndRebindsChangedTables(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, null, $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli('127.0.0.1', 'root', '', null, $server->port);
                $observe = static function (mysqli $database): array {
                    array_map($database->query(...), ['SET NAMES utf8mb4', "SET sql_mode=''", 'CREATE DATABASE a', 'CREATE DATABASE b', 'USE a', 'CREATE TABLE `猫` (`猫` INT)', 'INSERT INTO `猫` VALUES (7)']);
                    $prepared = $database->prepare('SELECT *, `猫`+?, "猫", 1||2, DATABASE(), CHARSET(\'猫\'), @@character_set_client FROM `猫`');
                    self::assertInstanceOf(mysqli_stmt::class, $prepared);
                    $argument = 2;
                    $prepared->bind_param('i', $argument);
                    $prepared->execute();
                    $initial = $prepared->get_result();
                    self::assertInstanceOf(mysqli_result::class, $initial);
                    $first = (new Native())->result($initial);
                    array_map($database->query(...), ['SET NAMES latin1', 'USE b', "SET sql_mode='ANSI_QUOTES,PIPES_AS_CONCAT'"]);
                    $argument = 3;
                    $prepared->execute();
                    $changed = $prepared->get_result();
                    self::assertInstanceOf(mysqli_result::class, $changed);
                    $second = (new Native())->result($changed);
                    array_map($database->query(...), ['SET NAMES utf8mb4', 'ALTER TABLE a.`猫` ADD other INT DEFAULT 9']);
                    $prepared->execute();
                    $altered = $prepared->get_result();
                    self::assertInstanceOf(mysqli_result::class, $altered);
                    $third = (new Native())->result($altered);
                    $prepared->close();
                    $database->close();
                    return [$first, $second, $third];
                };
                self::assertSame($observe($native), $observe($memory), $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
