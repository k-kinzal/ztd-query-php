<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;

/**
 * Tests for empty fixture handling.
 *
 * These tests verify that tables with schema but no data
 * return empty result sets correctly.
 */
final class EmptyFixtureTest extends MySqlIntegrationTestCase
{
    public function testSelectFromEmptyTableReturnsEmptyResult(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }

    public function testSelectWithWhereFromEmptyTableReturnsEmptyResult(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }

    public function testCountFromEmptyTableReturnsZero(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $rawRows = $this->rawQuery("SELECT COUNT(*) as cnt FROM `{$table}`");
        $ztdRows = $this->ztdQuery("SELECT COUNT(*) as cnt FROM `{$table}`");

        $this->assertEquals($rawRows, $ztdRows);
        $this->assertEquals(0, $ztdRows[0]['cnt']);
    }

    public function testJoinWithEmptyTableReturnsEmptyResult(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice')");

        $rawRows = $this->rawQuery("SELECT u.name, o.total FROM `{$users}` u JOIN `{$orders}` o ON u.id = o.user_id");
        $ztdRows = $this->ztdQuery("SELECT u.name, o.total FROM `{$users}` u JOIN `{$orders}` o ON u.id = o.user_id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }

    public function testLeftJoinWithEmptyRightTableReturnsLeftRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rawRows = $this->rawQuery("SELECT u.name, o.total FROM `{$users}` u LEFT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, o.total FROM `{$users}` u LEFT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
        $this->assertNull($ztdRows[0]['total']);
        $this->assertNull($ztdRows[1]['total']);
    }

    public function testInsertIntoEmptyTableAndSelect(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testAggregationOnEmptyTable(): void
    {
        $table = $this->uniqueTableName('sales');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, amount DECIMAL(10,2))");

        $rawRows = $this->rawQuery("SELECT SUM(amount) as total, AVG(amount) as avg_amount FROM `{$table}`");
        $ztdRows = $this->ztdQuery("SELECT SUM(amount) as total, AVG(amount) as avg_amount FROM `{$table}`");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertNull($ztdRows[0]['total']);
        $this->assertNull($ztdRows[0]['avg_amount']);
    }

    public function testGroupByOnEmptyTableReturnsEmptyResult(): void
    {
        $table = $this->uniqueTableName('sales');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, region VARCHAR(50), amount DECIMAL(10,2))");

        $rawRows = $this->rawQuery("SELECT region, SUM(amount) as total FROM `{$table}` GROUP BY region");
        $ztdRows = $this->ztdQuery("SELECT region, SUM(amount) as total FROM `{$table}` GROUP BY region");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }
}
