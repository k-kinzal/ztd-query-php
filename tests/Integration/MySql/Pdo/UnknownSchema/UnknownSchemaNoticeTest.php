<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\UnknownSchema;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * Tests for UnknownSchemaBehavior::Notice mode.
 *
 * In Notice mode, queries referencing unknown tables/columns trigger
 * a user notice and return an empty result set.
 */
final class UnknownSchemaNoticeTest extends MySqlIntegrationTestCase
{
    protected function getZtdConfig(): ZtdConfig
    {
        return new ZtdConfig(
            unknownSchemaBehavior: UnknownSchemaBehavior::Notice
        );
    }

    public function testSelectFromNonExistentTableTriggersNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $stmt = $this->ztdPdo->query('SELECT * FROM nonexistent_table_xyz123');

            $this->assertNotFalse($stmt);
            $rows = $stmt->fetchAll();
            $this->assertCount(0, $rows);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }

    public function testSelectWithJoinToNonExistentTableTriggersNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $table = $this->uniqueTableName('users');
            $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
            $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

            $stmt = $this->ztdPdo->query("SELECT * FROM `{$table}` JOIN nonexistent_table ON true");

            $this->assertNotFalse($stmt);
            $rows = $stmt->fetchAll();
            $this->assertCount(0, $rows);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }

    public function testInsertToExistingTableDoesNotTriggerNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $table = $this->uniqueTableName('users');
            $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

            $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

            $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
            $this->assertCount(1, $rows);
            $this->assertCount(0, $capturedNotices);
        } finally {
            restore_error_handler();
        }
    }

    public function testUpdateExistingTableDoesNotTriggerNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $table = $this->uniqueTableName('users');
            $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

            // Insert via shadow first
            $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

            // Update the shadow row
            $this->ztdPdo->exec("UPDATE `{$table}` SET name = 'Bob' WHERE id = 1");

            $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
            $this->assertCount(1, $rows);
            $this->assertEquals('Bob', $rows[0]['name']);
            $this->assertCount(0, $capturedNotices);
        } finally {
            restore_error_handler();
        }
    }

    public function testDeleteFromExistingTableDoesNotTriggerNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $table = $this->uniqueTableName('users');
            $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

            // Insert via shadow first
            $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

            // Delete the shadow row
            $this->ztdPdo->exec("DELETE FROM `{$table}` WHERE id = 1");

            $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
            $this->assertCount(0, $rows);
            $this->assertCount(0, $capturedNotices);
        } finally {
            restore_error_handler();
        }
    }

    public function testSelectExistingTableDoesNotTriggerNotice(): void
    {
        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $table = $this->uniqueTableName('users');
            $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
            $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

            $rows = $this->ztdQuery("SELECT * FROM `{$table}`");

            $this->assertCount(1, $rows);
            $this->assertCount(0, $capturedNotices);
        } finally {
            restore_error_handler();
        }
    }
}
