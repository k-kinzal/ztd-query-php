<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectWhereTest extends MySqlIntegrationTestCase
{
    public function testSelectWhereFiltersRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE age > 28");

        $this->assertCount(2, $rows);
    }

    public function testSelectWhereWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` WHERE age > 28 ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE age > 28 ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectWhereWithMultipleConditions(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT, active TINYINT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30, 1), (2, 'Bob', 25, 0), (3, 'Charlie', 35, 1)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` WHERE age > 28 AND active = 1 ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE age > 28 AND active = 1 ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
    }
}
