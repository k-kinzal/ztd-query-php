<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectOffsetTest extends MySqlIntegrationTestCase
{
    public function testSelectOffsetSkipsRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id LIMIT 2 OFFSET 1");

        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    public function testSelectOffsetWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id LIMIT 2 OFFSET 1");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id LIMIT 2 OFFSET 1");

        $this->assertSame($rawRows, $ztdRows);
    }
}
