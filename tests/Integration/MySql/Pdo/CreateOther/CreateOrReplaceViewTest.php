<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateOther;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class CreateOrReplaceViewTest extends MySqlIntegrationTestCase
{
    public function testCreateOrReplaceViewThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('users');
        $view = $this->uniqueTableName('users_view');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("CREATE OR REPLACE VIEW `{$view}` AS SELECT * FROM `{$table}`");
    }

    public function testCreateOrReplaceViewIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('users');
        $view = $this->uniqueTableName('users_view');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("CREATE OR REPLACE VIEW `{$view}` AS SELECT * FROM `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testCreateOrReplaceViewTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('users');
        $view = $this->uniqueTableName('users_view');

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
            $result = $ztdPdo->exec("CREATE OR REPLACE VIEW `{$view}` AS SELECT * FROM `{$table}`");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('CREATE OR REPLACE VIEW', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
