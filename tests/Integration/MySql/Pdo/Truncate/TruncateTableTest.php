<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Truncate;

use Tests\Integration\MySqlIntegrationTestCase;

final class TruncateTableTest extends MySqlIntegrationTestCase
{
    public function testTruncateTableRemovesAllRowsFromShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (name) VALUES ('Alice'), ('Bob'), ('Charlie')");

        $this->ztdPdo->exec("TRUNCATE TABLE `{$table}`");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $ztdRows);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(3, $rawRows);
    }

    public function testTruncateTableAllowsNewInserts(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdPdo->exec("TRUNCATE TABLE `{$table}`");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'NewUser')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('NewUser', $ztdRows[0]['name']);
        $this->assertSame(1, $ztdRows[0]['id']);
    }
}
