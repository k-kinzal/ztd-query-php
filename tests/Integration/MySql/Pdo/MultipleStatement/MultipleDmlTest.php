<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\MultipleStatement;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class MultipleDmlTest extends MySqlIntegrationTestCase
{
    public function testMultipleDmlStatementsThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("INSERT INTO `{$table}` VALUES (2, 'Bob', 'pending'); UPDATE `{$table}` SET status = 'active' WHERE id = 1");
    }

    public function testMultipleDmlStatementsIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");

        $result = $ztdPdo->exec("INSERT INTO `{$table}` VALUES (2, 'Bob', 'pending'); UPDATE `{$table}` SET status = 'active' WHERE id = 1");

        $this->assertSame(0, $result);
    }

    public function testMultipleDmlStatementsTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("INSERT INTO `{$table}` VALUES (2, 'Bob', 'pending'); UPDATE `{$table}` SET status = 'active' WHERE id = 1");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }

    public function testInsertUpdateDeleteSequenceThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('items');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, value INT)");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 100), (2, 200), (3, 300); UPDATE `{$table}` SET value = 150 WHERE id = 1; DELETE FROM `{$table}` WHERE id = 3");
    }

    public function testInsertUpdateDeleteSequenceIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('items');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, value INT)");

        $result = $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 100), (2, 200), (3, 300); UPDATE `{$table}` SET value = 150 WHERE id = 1; DELETE FROM `{$table}` WHERE id = 3");

        $this->assertSame(0, $result);
    }

    public function testInsertUpdateDeleteSequenceTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('items');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, value INT)");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 100), (2, 200), (3, 300); UPDATE `{$table}` SET value = 150 WHERE id = 1; DELETE FROM `{$table}` WHERE id = 3");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
