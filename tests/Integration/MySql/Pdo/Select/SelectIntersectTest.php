<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class SelectIntersectTest extends MySqlIntegrationTestCase
{
    public function testSelectIntersectThrowsExceptionInExceptionMode(): void
    {
        $table1 = $this->uniqueTableName('users1');
        $table2 = $this->uniqueTableName('users2');

        $this->rawPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$table2}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table1}` VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
        $this->rawPdo->exec("INSERT INTO `{$table2}` VALUES (1, 'Bob'), (2, 'David')");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SELECT name FROM `{$table1}` INTERSECT SELECT name FROM `{$table2}` ORDER BY name");
    }

    public function testSelectIntersectIsIgnoredInIgnoreMode(): void
    {
        $table1 = $this->uniqueTableName('users1');
        $table2 = $this->uniqueTableName('users2');

        $this->rawPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$table2}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table1}` VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
        $this->rawPdo->exec("INSERT INTO `{$table2}` VALUES (1, 'Bob'), (2, 'David')");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->query("SELECT name FROM `{$table1}` INTERSECT SELECT name FROM `{$table2}` ORDER BY name");

        $this->assertFalse($result);
    }

    public function testSelectIntersectTriggersNoticeInNoticeMode(): void
    {
        $table1 = $this->uniqueTableName('users1');
        $table2 = $this->uniqueTableName('users2');

        $this->rawPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$table2}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table1}` VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
        $this->rawPdo->exec("INSERT INTO `{$table2}` VALUES (1, 'Bob'), (2, 'David')");

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
            $result = $ztdPdo->query("SELECT name FROM `{$table1}` INTERSECT SELECT name FROM `{$table2}` ORDER BY name");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('INTERSECT', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
