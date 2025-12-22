<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Integration tests for SELECT DISTINCT.
 */
final class SelectDistinctTest extends MySqlIntegrationTestCase
{
    public function testSelectDistinctRemovesDuplicates(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), city VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'Tokyo'), (2, 'Bob', 'Tokyo'), (3, 'Charlie', 'Osaka')");

        $rawRows = $this->rawQuery("SELECT DISTINCT city FROM `{$table}` ORDER BY city");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT city FROM `{$table}` ORDER BY city");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectDistinctWithMultipleColumns(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, user_id INT, status VARCHAR(50))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 1, 'pending'), (2, 1, 'pending'), (3, 1, 'shipped'), (4, 2, 'pending')");

        $rawRows = $this->rawQuery("SELECT DISTINCT user_id, status FROM `{$table}` ORDER BY user_id, status");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT user_id, status FROM `{$table}` ORDER BY user_id, status");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(3, $ztdRows);
    }

    public function testSelectAllReturnsAllRowsIncludingDuplicates(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, city VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Tokyo'), (2, 'Tokyo'), (3, 'Osaka')");

        // ALL is the default, so duplicates are returned
        $rawRows = $this->rawQuery("SELECT ALL city FROM `{$table}` ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT ALL city FROM `{$table}` ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(3, $ztdRows);
    }

    public function testSelectDistinctWithNullValues(): void
    {
        $table = $this->uniqueTableName('data');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, value VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A'), (2, NULL), (3, NULL), (4, 'B')");

        $rawRows = $this->rawQuery("SELECT DISTINCT value FROM `{$table}` ORDER BY value");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT value FROM `{$table}` ORDER BY value");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(3, $ztdRows); // NULL, 'A', 'B'
    }

    public function testSelectDistinctWithJoin(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, product VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 'Widget'), (2, 1, 'Widget'), (3, 2, 'Gadget')");

        $rawRows = $this->rawQuery("SELECT DISTINCT u.name, o.product FROM `{$users}` u JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.name, o.product");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT u.name, o.product FROM `{$users}` u JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.name, o.product");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectDistinctWithAggregation(): void
    {
        $table = $this->uniqueTableName('sales');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, region VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'East', 100), (2, 'East', 200), (3, 'West', 150), (4, 'West', 150)");

        $rawRows = $this->rawQuery("SELECT DISTINCT region, SUM(amount) as total FROM `{$table}` GROUP BY region ORDER BY region");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT region, SUM(amount) as total FROM `{$table}` GROUP BY region ORDER BY region");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectDistinctWithSubquery(): void
    {
        $table = $this->uniqueTableName('data');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), value INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 10), (2, 'A', 20), (3, 'B', 10), (4, 'B', 30)");

        $rawRows = $this->rawQuery("SELECT DISTINCT category FROM `{$table}` WHERE value IN (SELECT DISTINCT value FROM `{$table}` WHERE value > 15) ORDER BY category");
        $ztdRows = $this->ztdQuery("SELECT DISTINCT category FROM `{$table}` WHERE value IN (SELECT DISTINCT value FROM `{$table}` WHERE value > 15) ORDER BY category");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }
}
