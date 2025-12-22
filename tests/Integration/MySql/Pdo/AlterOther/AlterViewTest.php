<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterOther;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class AlterViewTest extends MySqlIntegrationTestCase
{
    public function testAlterViewThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE VIEW v_{$table} AS SELECT * FROM `{$table}`");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("ALTER VIEW v_{$table} AS SELECT id FROM `{$table}`");
    }

    public function testAlterViewIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('users_ignore');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE VIEW v_{$table} AS SELECT * FROM `{$table}`");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("ALTER VIEW v_{$table} AS SELECT id FROM `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testAlterViewTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('users_notice');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE VIEW v_{$table} AS SELECT * FROM `{$table}`");

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
            $result = $ztdPdo->exec("ALTER VIEW v_{$table} AS SELECT id FROM `{$table}`");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('ALTER VIEW', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
