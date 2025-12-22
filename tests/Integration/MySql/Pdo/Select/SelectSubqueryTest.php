<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectSubqueryTest extends MySqlIntegrationTestCase
{
    public function testSelectWithSubqueryInWhere(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, total DECIMAL(10,2))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 100.00)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$users}` WHERE id IN (SELECT user_id FROM `{$orders}`) ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$users}` WHERE id IN (SELECT user_id FROM `{$orders}`) ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(1, $ztdRows);
    }

    public function testSelectWithSubqueryInFrom(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $rawRows = $this->rawQuery("SELECT * FROM (SELECT name, age FROM `{$table}` WHERE age > 20) AS sub ORDER BY name");
        $ztdRows = $this->ztdQuery("SELECT * FROM (SELECT name, age FROM `{$table}` WHERE age > 20) AS sub ORDER BY name");

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectWithScalarSubquery(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $rawRows = $this->rawQuery("SELECT name, (SELECT MAX(age) FROM `{$table}`) as max_age FROM `{$table}` ORDER BY name");
        $ztdRows = $this->ztdQuery("SELECT name, (SELECT MAX(age) FROM `{$table}`) as max_age FROM `{$table}` ORDER BY name");

        $this->assertSame($rawRows, $ztdRows);
    }
}
