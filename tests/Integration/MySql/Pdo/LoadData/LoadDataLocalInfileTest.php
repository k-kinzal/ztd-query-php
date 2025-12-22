<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\LoadData;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class LoadDataLocalInfileTest extends MySqlIntegrationTestCase
{
    public function testLoadDataLocalInfileThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("LOAD DATA LOCAL INFILE '/tmp/data.csv' INTO TABLE `{$table}`");
    }

    public function testLoadDataLocalInfileIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $result = $ztdPdo->exec("LOAD DATA LOCAL INFILE '/tmp/data.csv' INTO TABLE `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testLoadDataLocalInfileTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("LOAD DATA LOCAL INFILE '/tmp/data.csv' INTO TABLE `{$table}`");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('LOAD DATA', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
