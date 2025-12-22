<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\InfoRetrieval;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class ShowCreateTriggerTest extends MySqlIntegrationTestCase
{
    public function testShowCreateTriggerThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('users');
        $trigger = $this->uniqueTableName('test_trigger');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `{$table}` FOR EACH ROW SET NEW.name = UPPER(NEW.name)");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SHOW CREATE TRIGGER `{$trigger}`");
    }

    public function testShowCreateTriggerIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('users');
        $trigger = $this->uniqueTableName('test_trigger');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `{$table}` FOR EACH ROW SET NEW.name = UPPER(NEW.name)");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->query("SHOW CREATE TRIGGER `{$trigger}`");

        $this->assertFalse($result);
    }

    public function testShowCreateTriggerTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('users');
        $trigger = $this->uniqueTableName('test_trigger');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `{$table}` FOR EACH ROW SET NEW.name = UPPER(NEW.name)");

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
            $result = $ztdPdo->query("SHOW CREATE TRIGGER `{$trigger}`");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('SHOW CREATE TRIGGER', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
