<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Integration tests for SELECT with NATURAL JOIN.
 */
final class SelectNaturalJoinTest extends MySqlIntegrationTestCase
{
    public function testSelectNaturalJoinMatchesOnCommonColumns(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (order_id INT PRIMARY KEY, id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00), (2, 1, 200.00), (3, 2, 50.00)");

        $rawRows = $this->rawQuery("SELECT name, total FROM `{$users}` NATURAL JOIN `{$orders}` ORDER BY order_id");
        $ztdRows = $this->ztdQuery("SELECT name, total FROM `{$users}` NATURAL JOIN `{$orders}` ORDER BY order_id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(3, $ztdRows);
    }

    public function testSelectNaturalLeftJoinReturnsAllLeftRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (order_id INT PRIMARY KEY, id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00)");

        $rawRows = $this->rawQuery("SELECT name, total FROM `{$users}` NATURAL LEFT JOIN `{$orders}` ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT name, total FROM `{$users}` NATURAL LEFT JOIN `{$orders}` ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectNaturalRightJoinReturnsAllRightRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (order_id INT PRIMARY KEY, id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00), (2, 99, 200.00)");

        $rawRows = $this->rawQuery("SELECT name, total FROM `{$users}` NATURAL RIGHT JOIN `{$orders}` ORDER BY order_id");
        $ztdRows = $this->ztdQuery("SELECT name, total FROM `{$users}` NATURAL RIGHT JOIN `{$orders}` ORDER BY order_id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectNaturalJoinWithNoCommonColumnsReturnsCrossProduct(): void
    {
        $users = $this->uniqueTableName('users');
        $colors = $this->uniqueTableName('colors');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (user_id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$colors}` (color_id INT PRIMARY KEY, color VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$colors}` VALUES (1, 'Red'), (2, 'Blue')");

        // No common columns, so NATURAL JOIN becomes CROSS JOIN
        $rawRows = $this->rawQuery("SELECT name, color FROM `{$users}` NATURAL JOIN `{$colors}` ORDER BY user_id, color_id");
        $ztdRows = $this->ztdQuery("SELECT name, color FROM `{$users}` NATURAL JOIN `{$colors}` ORDER BY user_id, color_id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(4, $ztdRows); // 2 x 2 = 4
    }

    public function testSelectNaturalJoinWithNoMatchingRowsReturnsEmpty(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (order_id INT PRIMARY KEY, id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 99, 100.00)"); // No matching id

        $rawRows = $this->rawQuery("SELECT name, total FROM `{$users}` NATURAL JOIN `{$orders}`");
        $ztdRows = $this->ztdQuery("SELECT name, total FROM `{$users}` NATURAL JOIN `{$orders}`");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }
}
