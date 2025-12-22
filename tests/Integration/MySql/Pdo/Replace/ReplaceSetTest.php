<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Replace;

use Tests\Integration\MySqlIntegrationTestCase;

final class ReplaceSetTest extends MySqlIntegrationTestCase
{
    public function testReplaceSetSyntaxInsertsRow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");

        $this->ztdPdo->exec("REPLACE INTO `{$table}` SET id = 1, name = 'Alice', age = 30");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
    }

    public function testReplaceSetSyntaxReplacesExistingRow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 25)");

        $this->ztdPdo->exec("REPLACE INTO `{$table}` SET id = 1, name = 'Alice', age = 30");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertSame(30, $ztdRows[0]['age']);
    }
}
