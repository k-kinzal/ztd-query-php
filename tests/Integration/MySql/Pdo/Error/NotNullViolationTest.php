<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for NOT NULL constraint handling.
 *
 * These tests document how ZTD handles NOT NULL constraints.
 * ZTD does NOT validate NOT NULL constraints before execution -
 * writes are stored in shadow. Constraint validation would only
 * occur when changes are applied to the real database.
 */
final class NotNullViolationTest extends MySqlIntegrationTestCase
{
    public function testInsertNullIntoNotNullColumnStoredInShadow(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        // ZTD does not validate NOT NULL - insert is stored in shadow
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, NULL)");

        // Row is visible in ZTD with NULL value
        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['name']);

        // Row is not in physical table
        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testUpdateToNullStoredInShadow(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        // First insert a valid row via shadow
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        // Update to NULL
        $this->ztdPdo->exec("UPDATE `{$table}` SET name = NULL WHERE id = 1");

        // Updated row is visible in ZTD
        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    public function testInsertValidValueIntoNotNullColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testNullableColumnAcceptsNull(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Nullable column should accept NULL
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, NULL)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['name']);
    }

    public function testInsertEmptyStringIntoNotNullSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        // Empty string is not NULL
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, '')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('', $rows[0]['name']);
    }

    public function testMultipleNotNullColumnsPartiallyNull(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL
        )");

        // ZTD allows NULL in NOT NULL columns (stored in shadow)
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, first_name, last_name) VALUES (1, 'Alice', NULL)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['first_name']);
        $this->assertNull($rows[0]['last_name']);
    }

    public function testUpdateShadowRowToNullSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        // Insert via shadow first
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        // Update shadow row to NULL
        $this->ztdPdo->exec("UPDATE `{$table}` SET name = NULL WHERE id = 1");

        // Updated row is visible in ZTD with NULL value
        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['name']);

        // Physical table is empty
        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }
}
