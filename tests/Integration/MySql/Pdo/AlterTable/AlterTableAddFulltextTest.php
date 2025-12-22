<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class AlterTableAddFulltextTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddFulltextThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('articles');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, content TEXT) ENGINE=InnoDB");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("ALTER TABLE `{$table}` ADD FULLTEXT ft_content (content)");
    }

    public function testAlterTableAddFulltextIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('articles');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, content TEXT) ENGINE=InnoDB");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("ALTER TABLE `{$table}` ADD FULLTEXT ft_content (content)");

        $this->assertSame(0, $result);
    }

    public function testAlterTableAddFulltextTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('articles');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, content TEXT) ENGINE=InnoDB");

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
            $result = $ztdPdo->exec("ALTER TABLE `{$table}` ADD FULLTEXT ft_content (content)");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('ALTER TABLE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
