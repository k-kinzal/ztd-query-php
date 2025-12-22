<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Exception;
use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for SQL syntax errors.
 *
 * These tests verify that SQL syntax errors are properly detected
 * and reported.
 */
final class SqlParseErrorTest extends MySqlIntegrationTestCase
{
    public function testInvalidSqlSyntaxThrowsException(): void
    {
        $this->expectException(Exception::class);

        // Typo in SELECT keyword
        $this->ztdPdo->query('SELEC * FROM users');
    }

    public function testIncompleteSelectThrowsException(): void
    {
        $this->expectException(Exception::class);

        // Incomplete SELECT statement
        $this->ztdPdo->query('SELECT FROM');
    }

    public function testMalformedWhereClauseThrowsException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->expectException(Exception::class);

        // Malformed WHERE clause
        $this->ztdPdo->query("SELECT * FROM `{$table}` WHERE");
    }

    public function testMissingTableNameThrowsException(): void
    {
        $this->expectException(Exception::class);

        // Missing table name
        $this->ztdPdo->query('SELECT * FROM');
    }

    public function testInvalidInsertSyntaxThrowsException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->expectException(Exception::class);

        // Invalid INSERT syntax
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES");
    }

    public function testValidSyntaxSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");

        $this->assertCount(1, $rows);
    }

    public function testComplexValidQuerySucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $rows = $this->ztdQuery("
            SELECT name, age
            FROM `{$table}`
            WHERE age > 20
            ORDER BY age DESC
            LIMIT 10
        ");

        $this->assertCount(2, $rows);
    }
}
