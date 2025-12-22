<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class DeleteCteTest extends MySqlIntegrationTestCase
{
    public function testDeleteWithCteThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 100), (2, 'Bob', 50), (3, 'Charlie', 75)");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("WITH avg_score AS (SELECT AVG(score) as avg FROM `{$table}`) DELETE FROM `{$table}` WHERE score < (SELECT avg FROM avg_score)");
    }

    public function testDeleteWithCteIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 100), (2, 'Bob', 50), (3, 'Charlie', 75)");

        $result = $ztdPdo->exec("WITH avg_score AS (SELECT AVG(score) as avg FROM `{$table}`) DELETE FROM `{$table}` WHERE score < (SELECT avg FROM avg_score)");

        $this->assertSame(0, $result);
    }

    public function testDeleteWithCteTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 100), (2, 'Bob', 50), (3, 'Charlie', 75)");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("WITH avg_score AS (SELECT AVG(score) as avg FROM `{$table}`) DELETE FROM `{$table}` WHERE score < (SELECT avg FROM avg_score)");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('DELETE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
