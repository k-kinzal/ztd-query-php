<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;

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

        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 999)");

        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(999, $rows[0]['department_id']);

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

        $this->ztdPdo->exec("INSERT INTO `{$users}` (id, name, department_id) VALUES (1, 'Alice', 1)");

        $this->ztdPdo->exec("UPDATE `{$users}` SET department_id = 999 WHERE id = 1");

        $rows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertGreaterThanOrEqual(1, count($rows));
        $this->assertEquals(999, $rows[0]['department_id']);

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

        $this->ztdPdo->exec("DELETE FROM `{$departments}` WHERE id = 1");

        $deptRows = $this->ztdQuery("SELECT * FROM `{$departments}`");
        $this->assertCount(0, $deptRows);

        $userRows = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $userRows);
        $this->assertEquals(1, $userRows[0]['department_id']);

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

        $this->ztdPdo->exec("DELETE FROM `{$departments}` WHERE id = 1");

        $rows = $this->ztdQuery("
            SELECT u.name, d.name as dept_name
            FROM `{$users}` u
            JOIN `{$departments}` d ON u.department_id = d.id
        ");
        $this->assertCount(0, $rows);

        $leftRows = $this->ztdQuery("
            SELECT u.name, d.name as dept_name
            FROM `{$users}` u
            LEFT JOIN `{$departments}` d ON u.department_id = d.id
        ");
        $this->assertCount(1, $leftRows);
        $this->assertNull($leftRows[0]['dept_name']);
    }
}
