<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * Tests for CREATE TABLE on existing tables.
 *
 * These tests document how ZTD handles CREATE TABLE operations.
 * Currently, CREATE TABLE without IF NOT EXISTS throws UnsupportedSqlException
 * when the table already exists in the physical database.
 */
final class TableAlreadyExistsTest extends MySqlIntegrationTestCase
{
    public function testCreateTableOnExistingPhysicalTableThrowsException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->expectException(\Exception::class);

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
    }

    public function testCreateTableIfNotExistsOnExistingTableDoesNotThrow(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $result = $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->assertSame(0, $result);
    }

    public function testCreateNewTableSucceeds(): void
    {
        $table = $this->uniqueTableName('new_users');

        $result = $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->assertSame(0, $result);
    }

    public function testCreateTableAfterDropSucceeds(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");
        $this->ztdPdo->exec("DROP TABLE `{$table}`");

        $result = $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->assertSame(0, $result);
    }

    public function testCreateTableOnShadowTableThrowsException(): void
    {
        $table = $this->uniqueTableName('shadow_only');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->expectException(\Exception::class);

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
    }

    public function testInsertIntoNewlyCreatedShadowTableSucceeds(): void
    {
        $table = $this->uniqueTableName('shadow_only');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testCreateTableIfNotExistsOnShadowTableDoesNotThrow(): void
    {
        $table = $this->uniqueTableName('shadow_only');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $result = $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->assertSame(0, $result);
    }
}
