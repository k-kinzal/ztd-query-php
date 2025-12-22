<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\MultipleStatement;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class MultipleDdlTest extends MySqlIntegrationTestCase
{
    public function testMultipleDdlStatementsThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table1 = $this->uniqueTableName('table1');
        $table2 = $this->uniqueTableName('table2');

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY); CREATE TABLE `{$table2}` (id INT PRIMARY KEY)");
    }

    public function testMultipleDdlStatementsIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table1 = $this->uniqueTableName('table1');
        $table2 = $this->uniqueTableName('table2');

        $result = $ztdPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY); CREATE TABLE `{$table2}` (id INT PRIMARY KEY)");

        $this->assertSame(0, $result);
    }

    public function testMultipleDdlStatementsTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table1 = $this->uniqueTableName('table1');
        $table2 = $this->uniqueTableName('table2');

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY); CREATE TABLE `{$table2}` (id INT PRIMARY KEY)");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }

    public function testCreateAlterDropSequenceThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('items');

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY); ALTER TABLE `{$table}` ADD COLUMN name VARCHAR(255); DROP TABLE `{$table}`");
    }

    public function testCreateAlterDropSequenceIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('items');

        $result = $ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY); ALTER TABLE `{$table}` ADD COLUMN name VARCHAR(255); DROP TABLE `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testCreateAlterDropSequenceTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('items');

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY); ALTER TABLE `{$table}` ADD COLUMN name VARCHAR(255); DROP TABLE `{$table}`");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
