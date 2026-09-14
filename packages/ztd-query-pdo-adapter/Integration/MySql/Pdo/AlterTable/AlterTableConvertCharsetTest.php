<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Support\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

final class AlterTableConvertCharsetTest extends MySqlIntegrationTestCase
{
    public function testAlterTableConvertCharsetThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(ZtdPdoException::class);

        $ztdPdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function testAlterTableConvertCharsetIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->assertSame(0, $result);
    }

    public function testAlterTableConvertCharsetTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('ALTER TABLE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
