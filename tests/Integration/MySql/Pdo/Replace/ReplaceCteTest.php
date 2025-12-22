<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Replace;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class ReplaceCteTest extends MySqlIntegrationTestCase
{
    public function testReplaceWithCteThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("WITH cte AS (SELECT * FROM `{$source}` WHERE id = 1) REPLACE INTO `{$target}` SELECT * FROM cte");
    }

    public function testReplaceWithCteIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice'), (2, 'Bob')");

        $result = $ztdPdo->exec("WITH cte AS (SELECT * FROM `{$source}` WHERE id = 1) REPLACE INTO `{$target}` SELECT * FROM cte");

        $this->assertSame(0, $result);
    }

    public function testReplaceWithCteTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice'), (2, 'Bob')");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("WITH cte AS (SELECT * FROM `{$source}` WHERE id = 1) REPLACE INTO `{$target}` SELECT * FROM cte");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('REPLACE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
