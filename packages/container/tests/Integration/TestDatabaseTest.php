<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Mysqli\MySql80Container;
use Container\Mysqli\MySql84Container;
use Container\Pdo\MySqlContainer;
use Container\Pdo\PostgreSqlContainer;
use mysqli;
use mysqli_result;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversClass(MySqlContainer::class)]
#[CoversClass(MySql80Container::class)]
#[CoversClass(MySql84Container::class)]
#[CoversClass(PostgreSqlContainer::class)]
final class TestDatabaseTest extends TestCase
{
    public function testPdoMySqlReusesItsNativeConnection(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $connection = $instance->getData(PDO::class);
        self::assertInstanceOf(PDO::class, $connection);
        $result = $connection->query('SELECT 1');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
        self::assertSame($connection, Testcontainers::run(MySqlContainer::class)->getData(PDO::class));
    }

    public function testPostgreSqlReusesItsNativeConnection(): void
    {
        $instance = Testcontainers::run(PostgreSqlContainer::class);
        $connection = $instance->getData(PDO::class);
        self::assertInstanceOf(PDO::class, $connection);
        $result = $connection->query('SELECT current_database()');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('ztd_test', $result->fetchColumn());
        self::assertSame($connection, Testcontainers::run(PostgreSqlContainer::class)->getData(PDO::class));
    }

    public function testMysqli80InitializesItsTestDatabase(): void
    {
        $instance = Testcontainers::run(MySql80Container::class);
        $connection = $instance->getData(mysqli::class);
        self::assertInstanceOf(mysqli::class, $connection);
        $result = $connection->query('SELECT DATABASE()');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame(['test'], $result->fetch_row());
        self::assertSame('utf8mb4', $connection->character_set_name());
    }

    public function testMysqli84RestartsWithAnEmptyTestDatabase(): void
    {
        $first = Testcontainers::run(MySql84Container::class);
        $firstConnection = $first->getData(mysqli::class);
        self::assertInstanceOf(mysqli::class, $firstConnection);
        $firstConnection->query('CREATE TABLE isolation_probe (id INT)');

        $second = Testcontainers::run(MySql84Container::class);
        $secondConnection = $second->getData(mysqli::class);
        self::assertInstanceOf(mysqli::class, $secondConnection);
        self::assertNotSame($firstConnection, $secondConnection);
        self::assertTrue($secondConnection->query('CREATE TABLE isolation_probe (id INT)'));
    }
}
