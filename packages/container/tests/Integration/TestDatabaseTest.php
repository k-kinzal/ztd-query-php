<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\MySql80Container;
use Container\MySql84Container;
use Container\MySqlConfiguration;
use Container\PostgreSql16Container;
use Container\PostgreSql17Container;
use Container\PostgreSqlConfiguration;
use mysqli;
use mysqli_result;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Testcontainers;

#[CoversClass(MySqlConfiguration::class)]
#[CoversClass(PostgreSqlConfiguration::class)]
#[CoversClass(MySql80Container::class)]
#[CoversClass(MySql84Container::class)]
#[CoversClass(PostgreSql16Container::class)]
#[CoversClass(PostgreSql17Container::class)]
#[Large]
final class TestDatabaseTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<GenericContainer>}>
     */
    public static function providerMySqlVersions(): iterable
    {
        yield '8.0' => [MySql80Container::class];
        yield '8.4' => [MySql84Container::class];
    }

    /**
     * @return iterable<string, array{class-string<GenericContainer>}>
     */
    public static function providerPostgreSqlVersions(): iterable
    {
        yield '16' => [PostgreSql16Container::class];
        yield '17' => [PostgreSql17Container::class];
    }

    /**
     * @param class-string<GenericContainer> $class
     */
    #[DataProvider('providerMySqlVersions')]
    public function testSameMySqlInstanceSupportsPdoAndMysqli(string $class): void
    {
        $instance = Testcontainers::run($class);
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
        $port = $instance->getMappedPort(3306);
        self::assertNotNull($port);
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=test;charset=utf8mb4", 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $mysqli = new mysqli($host, 'root', 'root', 'test', $port);
        $mysqli->set_charset('utf8mb4');
        $pdo->exec('CREATE TABLE shared_connection (id INT PRIMARY KEY)');
        $pdo->exec('INSERT INTO shared_connection VALUES (42)');
        $result = $mysqli->query('SELECT id FROM shared_connection');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame(['42'], $result->fetch_row());
        self::assertSame($instance, Testcontainers::run($class));
        $pdo->exec('DROP TABLE shared_connection');
    }

    /**
     * @param class-string<GenericContainer> $class
     */
    #[DataProvider('providerPostgreSqlVersions')]
    public function testPostgreSqlStartsWithTheCommonDatabase(string $class): void
    {
        $instance = Testcontainers::run($class);
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
        $port = $instance->getMappedPort(5432);
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=test", 'test', 'test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $result = $pdo->query('SELECT current_database()');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('test', $result->fetchColumn());
        self::assertSame($instance, Testcontainers::run($class));
    }
}
