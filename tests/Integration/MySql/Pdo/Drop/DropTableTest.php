<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Drop;

use Tests\Integration\MySqlIntegrationTestCase;

final class DropTableTest extends MySqlIntegrationTestCase
{
    public function testDropTableRemovesFromSchema(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("DROP TABLE `{$table}`");

        $this->expectException(\RuntimeException::class);
        $this->ztdPdo->query("SELECT * FROM `{$table}`");
    }

    public function testDropTableRawDbUnchanged(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("DROP TABLE `{$table}`");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rawRows);
    }
}
