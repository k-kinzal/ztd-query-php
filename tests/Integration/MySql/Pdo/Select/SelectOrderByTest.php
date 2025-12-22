<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectOrderByTest extends MySqlIntegrationTestCase
{
    public function testSelectOrderByAscendingSortsCorrectly(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Charlie', 35), (2, 'Alice', 30), (3, 'Bob', 25)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY age ASC");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY age ASC");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);
    }

    public function testSelectOrderByDescendingSortsCorrectly(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Charlie', 35), (2, 'Alice', 30), (3, 'Bob', 25)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY age DESC");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY age DESC");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertSame('Charlie', $ztdRows[0]['name']);
    }

    public function testSelectOrderByMultipleColumns(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT, score INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30, 100), (2, 'Bob', 30, 90), (3, 'Charlie', 25, 95)");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY age DESC, score ASC");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY age DESC, score ASC");

        $this->assertSame($rawRows, $ztdRows);
    }
}
