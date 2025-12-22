<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;

/**
 * Integration tests for SessionFactory injection in ZtdMysqli.
 *
 * Verifies that ZtdMysqli works correctly when a SessionFactory
 * is explicitly injected via fromMysqli().
 */
#[CoversNothing]
#[Large]
final class MysqliSessionFactoryInjectionTest extends TestCase
{
    public function testExplicitMySqlSessionFactoryInjectionWorks(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
            $factory = new MySqlSessionFactory();
            $ztd = ZtdMysqli::fromMysqli($rawMysqli, null, $factory);

            $result = $ztd->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryInsertIsVisibleViaSelect(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
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
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryDoesNotModifyPhysicalDatabase(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
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
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
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
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
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

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInjectedFactoryAffectedRowsTracking(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
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
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));
        try {
            $ztdDefault = ZtdMysqli::fromMysqli($rawMysqli);

            $resultDefault = $ztdDefault->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($resultDefault);
            self::assertInstanceOf(\mysqli_result::class, $resultDefault);

            /** @var list<array<string, mixed>> $rowsDefault */
            $rowsDefault = $resultDefault->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rowsDefault);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
