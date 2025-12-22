<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectFromTest extends MySqlIntegrationTestCase
{
    public function testSelectFromReturnsAllRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");

        $this->assertCount(2, $rows);
    }

    public function testSelectFromWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectSpecificColumnsFromTable(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 30)");

        $rawRows = $this->rawQuery("SELECT name, age FROM `{$table}`");
        $ztdRows = $this->ztdQuery("SELECT name, age FROM `{$table}`");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertArrayNotHasKey('id', $ztdRows[0]);
    }
}
