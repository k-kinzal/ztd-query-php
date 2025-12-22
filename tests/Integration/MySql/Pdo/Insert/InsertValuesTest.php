<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertValuesTest extends MySqlIntegrationTestCase
{
    public function testInsertSingleRowStoresInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testInsertReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->assertSame(1, $affected);
    }
}
