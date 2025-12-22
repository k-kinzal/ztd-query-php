<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class UpdateCteTest extends MySqlIntegrationTestCase
{
    public function testUpdateWithCteThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $users = $this->uniqueTableName('users');
        $scores = $this->uniqueTableName('scores');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255), bonus INT)");
        $this->rawPdo->exec("CREATE TABLE `{$scores}` (user_id INT, score INT)");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice', 0), (2, 'Bob', 0)");
        $this->rawPdo->exec("INSERT INTO `{$scores}` VALUES (1, 100), (2, 50)");

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("WITH high_scores AS (SELECT user_id FROM `{$scores}` WHERE score >= 80) UPDATE `{$users}` SET bonus = 10 WHERE id IN (SELECT user_id FROM high_scores)");
    }

    public function testUpdateWithCteIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $users = $this->uniqueTableName('users');
        $scores = $this->uniqueTableName('scores');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255), bonus INT)");
        $this->rawPdo->exec("CREATE TABLE `{$scores}` (user_id INT, score INT)");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice', 0), (2, 'Bob', 0)");
        $this->rawPdo->exec("INSERT INTO `{$scores}` VALUES (1, 100), (2, 50)");

        $result = $ztdPdo->exec("WITH high_scores AS (SELECT user_id FROM `{$scores}` WHERE score >= 80) UPDATE `{$users}` SET bonus = 10 WHERE id IN (SELECT user_id FROM high_scores)");

        $this->assertSame(0, $result);
    }

    public function testUpdateWithCteTriggersNoticeInNoticeMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $users = $this->uniqueTableName('users');
        $scores = $this->uniqueTableName('scores');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255), bonus INT)");
        $this->rawPdo->exec("CREATE TABLE `{$scores}` (user_id INT, score INT)");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice', 0), (2, 'Bob', 0)");
        $this->rawPdo->exec("INSERT INTO `{$scores}` VALUES (1, 100), (2, 50)");

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec("WITH high_scores AS (SELECT user_id FROM `{$scores}` WHERE score >= 80) UPDATE `{$users}` SET bonus = 10 WHERE id IN (SELECT user_id FROM high_scores)");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('UPDATE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
