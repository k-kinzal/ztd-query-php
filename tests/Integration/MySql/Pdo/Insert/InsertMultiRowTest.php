<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertMultiRowTest extends MySqlIntegrationTestCase
{
    public function testInsertMultipleRowsStoresInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(3, $ztdRows);
    }

    public function testInsertMultipleRowsReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

        $this->assertSame(2, $affected);
    }
}
