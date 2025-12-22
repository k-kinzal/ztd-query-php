<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteWhereTest extends MySqlIntegrationTestCase
{
    public function testDeleteSingleRowRemovesFromShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdPdo->exec("DELETE FROM `{$table}` WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testDeleteMultipleRowsRemovesFromShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'inactive'), (2, 'Bob', 'inactive'), (3, 'Charlie', 'active')");

        $affected = $this->ztdPdo->exec("DELETE FROM `{$table}` WHERE status = 'inactive'");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
