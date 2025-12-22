<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;

/**
 * Tests for duplicate key handling.
 *
 * These tests document how ZTD handles PRIMARY KEY and UNIQUE constraints.
 * ZTD does NOT validate these constraints before execution - writes are
 * stored in shadow. Constraint validation would only occur when changes
 * are applied to the real database.
 */
final class DuplicateKeyTest extends MySqlIntegrationTestCase
{
    public function testInsertDuplicatePrimaryKeyInShadowAllowed(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertGreaterThanOrEqual(1, count($rows));

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testInsertIgnoreOnShadowDuplicate(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("INSERT IGNORE INTO `{$table}` (id, name) VALUES (1, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertGreaterThanOrEqual(1, count($rows));
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testInsertOnDuplicateKeyUpdateUpdatesExisting(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Bob') ON DUPLICATE KEY UPDATE name = 'Bob'");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertGreaterThanOrEqual(1, count($rows));
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    public function testInsertDifferentPksSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (2, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(2, $rows);
    }

    public function testUpdatePrimaryKeyInShadow(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (2, 'Bob')");

        $this->ztdPdo->exec("UPDATE `{$table}` SET id = 1 WHERE id = 2");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY name");
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    public function testInsertDuplicateUniqueKeyInShadowAllowed(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, email) VALUES (1, 'alice@example.com')");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, email) VALUES (2, 'alice@example.com')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertGreaterThanOrEqual(1, count($rows));

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testMultipleShadowInsertsWithDifferentPks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'First')");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (2, 'Second')");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (3, 'Third')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(3, $rows);
    }

    public function testShadowRowVisibleWithPhysicalData(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Physical Alice')");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (2, 'Shadow Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertGreaterThanOrEqual(1, count($rows));

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rawRows);
        $this->assertEquals('Physical Alice', $rawRows[0]['name']);
    }
}
