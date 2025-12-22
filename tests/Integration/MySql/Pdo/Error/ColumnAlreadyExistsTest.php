<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for ALTER TABLE ADD COLUMN operations.
 *
 * These tests document how ZTD handles ALTER TABLE operations.
 * ZTD supports basic ALTER TABLE ADD COLUMN operations.
 */
final class ColumnAlreadyExistsTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddNewColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN age INT");

        $this->assertSame(0, $result);
    }

    public function testAlterTableAddMultipleNewColumnsSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN name VARCHAR(255), ADD COLUMN age INT");

        $this->assertSame(0, $result);
    }

    public function testAlterTableDropColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");

        // Drop a column
        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` DROP COLUMN age");

        $this->assertSame(0, $result);
    }

    public function testAlterTableModifyColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Modify column type
        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN name VARCHAR(500)");

        $this->assertSame(0, $result);
    }

    public function testAlterTableRenameColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Rename column (MySQL 8.0+ syntax)
        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` RENAME COLUMN name TO full_name");

        $this->assertSame(0, $result);
    }

    public function testInsertAfterAlterTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Add new column
        $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN age INT");

        // Insert into table with new column
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, age) VALUES (1, 'Alice', 30)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
        $this->assertEquals(30, $rows[0]['age']);
    }
}
