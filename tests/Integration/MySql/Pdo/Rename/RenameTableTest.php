<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Rename;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class RenameTableTest extends MySqlIntegrationTestCase
{
    public function testRenameTableThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $oldName = $this->uniqueTableName('old_users');
        $newName = $this->uniqueTableName('new_users');

        $this->rawPdo->exec("CREATE TABLE `{$oldName}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("RENAME TABLE `{$oldName}` TO `{$newName}`");
    }

    public function testRenameTableIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $oldName = $this->uniqueTableName('old_users');
        $newName = $this->uniqueTableName('new_users');

        $this->rawPdo->exec("CREATE TABLE `{$oldName}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $result = $ztdPdo->exec("RENAME TABLE `{$oldName}` TO `{$newName}`");

        $this->assertSame(0, $result);
    }

    public function testRenameTableTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $oldName = $this->uniqueTableName('old_users');
        $newName = $this->uniqueTableName('new_users');

        $this->rawPdo->exec("CREATE TABLE `{$oldName}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("RENAME TABLE `{$oldName}` TO `{$newName}`");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('RENAME TABLE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
