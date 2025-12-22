<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectJoinTest extends MySqlIntegrationTestCase
{
    public function testSelectInnerJoinReturnsMatchingRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00), (2, 1, 200.00)");

        $rawRows = $this->rawQuery("SELECT u.name, o.total FROM `{$users}` u INNER JOIN `{$orders}` o ON u.id = o.user_id ORDER BY o.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, o.total FROM `{$users}` u INNER JOIN `{$orders}` o ON u.id = o.user_id ORDER BY o.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectLeftJoinReturnsAllLeftRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00)");

        $rawRows = $this->rawQuery("SELECT u.name, o.total FROM `{$users}` u LEFT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, o.total FROM `{$users}` u LEFT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY u.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectRightJoinReturnsAllRightRows(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00), (2, 99, 200.00)");

        $rawRows = $this->rawQuery("SELECT u.name, o.total FROM `{$users}` u RIGHT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY o.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, o.total FROM `{$users}` u RIGHT JOIN `{$orders}` o ON u.id = o.user_id ORDER BY o.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }
}
