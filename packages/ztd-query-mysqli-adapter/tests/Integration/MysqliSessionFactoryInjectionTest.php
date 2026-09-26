<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;

#[\PHPUnit\Framework\Attributes\CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\ConnectionExecution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliStatementBindingBridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\MysqliResultProcessor::class)]
#[Large]
final class MysqliSessionFactoryInjectionTest extends TestCase
{
    public function testExplicitMySqlSessionFactoryInjectionWorks(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $result = $ztd->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(mysqli_result::class, $result);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rows);
        } finally {
            $container->stop();
        }
    }

    public function testInjectedFactoryInsertIsVisibleViaSelect(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $ztd->query(sprintf(
                "INSERT INTO `%s` (name) VALUES ('Charlie')",
                $table
            ));

            $result = $ztd->query(sprintf('SELECT * FROM `%s` ORDER BY name', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(mysqli_result::class, $result);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $container->stop();
        }
    }

    public function testInjectedFactoryDoesNotModifyPhysicalDatabase(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $ztd->query(sprintf(
                "INSERT INTO `%s` (name) VALUES ('Charlie')",
                $table
            ));

            $result = $rawMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY name', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(mysqli_result::class, $result);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(2, $rows);
            self::assertSame('Alice', $rows[0]['name']);
            self::assertSame('Bob', $rows[1]['name']);
        } finally {
            $container->stop();
        }
    }

    public function testInjectedFactoryPreparedStatementWorks(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $ztd->query(sprintf(
                "INSERT INTO `%s` (name) VALUES ('Charlie')",
                $table
            ));

            $stmt = $ztd->prepare(sprintf('SELECT * FROM `%s` WHERE name = ?', $table));
            self::assertNotFalse($stmt);

            $name = 'Charlie';
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            self::assertNotFalse($result);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $container->stop();
        }
    }

    public function testInjectedFactoryAffectedRowsTracking(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $ztd->query(sprintf(
                "INSERT INTO `%s` (name) VALUES ('Charlie')",
                $table
            ));

            self::assertSame(1, $ztd->lastAffectedRows());
        } finally {
            $container->stop();
        }
    }

    public function testDefaultFactoryBehaviorMatchesExplicitInjection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $rawMysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $rawMysqli->set_charset('utf8mb4');
            $table = 'prefix_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
            $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
            $ztdDefault = ZtdMysqli::fromMysqli($rawMysqli);

            $resultDefault = $ztdDefault->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($resultDefault);
            self::assertInstanceOf(mysqli_result::class, $resultDefault);

            $rowsDefault = $resultDefault->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rowsDefault);
        } finally {
            $container->stop();
        }
    }
}
