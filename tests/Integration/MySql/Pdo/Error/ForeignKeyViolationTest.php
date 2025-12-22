<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for FOREIGN KEY constraint handling.
 *
 * These tests document how ZTD handles FOREIGN KEY constraints.
 * ZTD does NOT validate FK constraints before execution - writes
 * are stored in shadow. FK validation would only occur when
 * changes are applied to the real database.
 */
final class ForeignKeyViolationTest extends MySqlIntegrationTestCase
{
    public function testInsertWithInvalidForeignKeyStoredInShadow(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");
        $this->rawPdo->exec("INSERT INTO `{$departments}` (id, name) VALUES (1, 'Engineering')");

        // ZTD does not validate FK constraints - insert is stored in shadow
        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 999)");

        // The row is visible in ZTD even with invalid FK
        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(999, $rows[0]['department_id']);

        // Row is not in physical table
        $rawRows = $this->rawQuery("SELECT * FROM `{$users}`");
        $this->assertCount(0, $rawRows);
    }

    public function testUpdateToInvalidForeignKeyStoredInShadow(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");
        $this->rawPdo->exec("INSERT INTO `{$departments}` (id, name) VALUES (1, 'Engineering')");

        // First insert via ZTD shadow (not physical)
        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 1)");

        // ZTD does not validate FK constraints - update is stored in shadow
        $this->ztdPdo->exec("UPDATE `{$users}` SET department_id = 999 WHERE id = 1");

        // The updated row is visible in ZTD
        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertGreaterThanOrEqual(1, count($rows));
        $this->assertEquals(999, $rows[0]['department_id']);

        // Physical table has no user rows (only shadow)
        $rawRows = $this->rawQuery("SELECT * FROM `{$users}`");
        $this->assertCount(0, $rawRows);
    }

    public function testInsertWithValidForeignKeySucceeds(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");
        $this->rawPdo->exec("INSERT INTO `{$departments}` (id, name) VALUES (1, 'Engineering')");

        // Inserting with valid foreign key should succeed
        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 1)");

        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['department_id']);
    }

    public function testInsertWithNullForeignKeySucceeds(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");

        // NULL foreign key should be allowed (unless NOT NULL)
        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', NULL)");

        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['department_id']);
    }

    public function testDeleteParentWithChildRowsStoredInShadow(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");
        $this->rawPdo->exec("INSERT INTO `{$departments}` (id, name) VALUES (1, 'Engineering')");
        $this->rawPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 1)");

        // ZTD marks parent as deleted in shadow (FK not validated)
        $this->ztdPdo->exec("DELETE FROM `{$departments}` WHERE id = 1");

        // Parent is not visible in ZTD
        $deptRows = $this->ztdQuery("SELECT * FROM `{$departments}`");
        $this->assertCount(0, $deptRows);

        // Child still exists and references deleted parent
        $userRows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $userRows);
        $this->assertEquals(1, $userRows[0]['department_id']);

        // Physical table still has both
        $rawDeptRows = $this->rawQuery("SELECT * FROM `{$departments}`");
        $this->assertCount(1, $rawDeptRows);
    }

    public function testJoinWithDeletedForeignKeyReturnsNoRows(): void
    {
        $departments = $this->uniqueTableName('departments');
        $users = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$departments}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$users}` (
            id INT PRIMARY KEY,
            name VARCHAR(255),
            department_id INT,
            FOREIGN KEY (department_id) REFERENCES `{$departments}`(id)
        )");
        $this->rawPdo->exec("INSERT INTO `{$departments}` (id, name) VALUES (1, 'Engineering')");
        $this->rawPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 1)");

        // Delete the parent
        $this->ztdPdo->exec("DELETE FROM `{$departments}` WHERE id = 1");

        // INNER JOIN returns no rows because parent is deleted
        $rows = $this->ztdQuery("
            SELECT u.name, d.name as dept_name
            FROM `{$users}` u
            JOIN `{$departments}` d ON u.department_id = d.id
        ");
        $this->assertCount(0, $rows);

        // LEFT JOIN returns user with NULL department
        $leftRows = $this->ztdQuery("
            SELECT u.name, d.name as dept_name
            FROM `{$users}` u
            LEFT JOIN `{$departments}` d ON u.department_id = d.id
        ");
        $this->assertCount(1, $leftRows);
        $this->assertNull($leftRows[0]['dept_name']);
    }
}
