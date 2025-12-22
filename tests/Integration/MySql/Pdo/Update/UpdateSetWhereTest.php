<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateSetWhereTest extends MySqlIntegrationTestCase
{
    public function testUpdateSingleRowModifiesShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 25), (2, 'Bob', 30)");

        $this->ztdPdo->exec("UPDATE `{$table}` SET age = 26 WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertSame(26, $ztdRows[0]['age']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testUpdateMultipleRowsModifiesShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'active'), (2, 'Bob', 'active'), (3, 'Charlie', 'inactive')");

        $affected = $this->ztdPdo->exec("UPDATE `{$table}` SET status = 'updated' WHERE status = 'active'");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE status = 'updated' ORDER BY id");
        $this->assertCount(2, $ztdRows);
    }

    public function testUpdateAllRowsWithoutWhere(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'active'), (2, 'Bob', 'active')");

        $affected = $this->ztdPdo->exec("UPDATE `{$table}` SET status = 'updated'");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE status = 'updated'");
        $this->assertCount(2, $ztdRows);
    }
}
