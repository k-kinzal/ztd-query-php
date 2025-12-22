<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectCteTest extends MySqlIntegrationTestCase
{
    public function testSelectWithSimpleCte(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $sql = "WITH cte AS (SELECT * FROM `{$table}` WHERE age > 20) SELECT * FROM cte ORDER BY name";
        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectWithMultipleCtes(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00), (2, 1, 200.00)");

        $sql = "WITH user_cte AS (SELECT * FROM `{$users}`), order_cte AS (SELECT * FROM `{$orders}`) SELECT u.name, o.total FROM user_cte u JOIN order_cte o ON u.id = o.user_id ORDER BY o.id";
        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
    }
}
