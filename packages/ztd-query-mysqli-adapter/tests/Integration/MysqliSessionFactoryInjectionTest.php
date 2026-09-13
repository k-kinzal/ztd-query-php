<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;

#[\PHPUnit\Framework\Attributes\CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliConnection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultProcessor::class)]
#[Large]
final class MysqliSessionFactoryInjectionTest extends TestCase
{
    public function testExplicitMySqlSessionFactoryInjectionWorks(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $result = $ztd->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(mysqli_result::class, $result);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryInsertIsVisibleViaSelect(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
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
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryDoesNotModifyPhysicalDatabase(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
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
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryPreparedStatementWorks(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
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
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryAffectedRowsTracking(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $ztd->query(sprintf(
                "INSERT INTO `%s` (name) VALUES ('Charlie')",
                $table
            ));

            self::assertSame(1, $ztd->lastAffectedRows());
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testDefaultFactoryBehaviorMatchesExplicitInjection(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
            $ztdDefault = ZtdMysqli::fromMysqli($rawMysqli);

            $resultDefault = $ztdDefault->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($resultDefault);
            self::assertInstanceOf(mysqli_result::class, $resultDefault);

            $rowsDefault = $resultDefault->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rowsDefault);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
