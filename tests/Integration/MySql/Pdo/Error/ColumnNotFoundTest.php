<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use PDOException;
use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for column not found errors.
 *
 * These tests verify that appropriate errors are thrown when
 * queries reference non-existent columns.
 */
final class ColumnNotFoundTest extends MySqlIntegrationTestCase
{
    public function testSelectNonExistentColumnThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Unknown column/");

        $this->ztdQuery("SELECT nonexistent_column FROM `{$table}`");
    }

    public function testInsertWithValidColumnsSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'value')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
    }

    public function testUpdateWithValidColumnsSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // First insert via shadow
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        // Update the shadow row
        $this->ztdPdo->exec("UPDATE `{$table}` SET name = 'Bob' WHERE id = 1");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    public function testSelectExistingColumnSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT name FROM `{$table}`");

        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testWhereClauseWithNonExistentColumnThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Unknown column/");

        $this->ztdQuery("SELECT * FROM `{$table}` WHERE nonexistent_column = 'value'");
    }

    public function testOrderByNonExistentColumnThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Unknown column/");

        $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY nonexistent_column");
    }

    public function testGroupByNonExistentColumnThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Unknown column/");

        $this->ztdQuery("SELECT nonexistent_column FROM `{$table}` GROUP BY nonexistent_column");
    }
}
