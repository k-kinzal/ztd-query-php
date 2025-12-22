<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectWindowTest extends MySqlIntegrationTestCase
{
    public function testSelectWithRowNumberWindow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $sql = "SELECT name, age, ROW_NUMBER() OVER (ORDER BY age) as rn FROM `{$table}` ORDER BY rn";
        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectWithSumOverWindow(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150)");

        $sql = "SELECT category, amount, SUM(amount) OVER (PARTITION BY category) as category_total FROM `{$table}` ORDER BY id";
        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectWithNamedWindow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $sql = "SELECT name, age, ROW_NUMBER() OVER w as rn FROM `{$table}` WINDOW w AS (ORDER BY age) ORDER BY rn";
        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
    }
}
