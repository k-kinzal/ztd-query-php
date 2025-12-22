<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\MultipleStatement;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class MultipleSelectTest extends MySqlIntegrationTestCase
{
    public function testMultipleSelectQueriesThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1; SELECT * FROM `{$table}` WHERE id = 2");
    }

    public function testMultipleSelectQueriesIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $result = $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1; SELECT * FROM `{$table}` WHERE id = 2");

        $this->assertFalse($result);
    }

    public function testMultipleSelectQueriesTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1; SELECT * FROM `{$table}` WHERE id = 2");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }

    public function testMultipleSelectWithAggregatesThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, price DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 100.00), (2, 200.00), (3, 300.00)");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SELECT SUM(price) as total FROM `{$table}`; SELECT COUNT(*) as cnt FROM `{$table}`");
    }

    public function testMultipleSelectWithAggregatesIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, price DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 100.00), (2, 200.00), (3, 300.00)");

        $result = $ztdPdo->query("SELECT SUM(price) as total FROM `{$table}`; SELECT COUNT(*) as cnt FROM `{$table}`");

        $this->assertFalse($result);
    }

    public function testMultipleSelectWithAggregatesTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, price DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 100.00), (2, 200.00), (3, 300.00)");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->query("SELECT SUM(price) as total FROM `{$table}`; SELECT COUNT(*) as cnt FROM `{$table}`");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
