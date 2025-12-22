<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableChangeColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableChangeColumnRenames(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` CHANGE COLUMN name full_name VARCHAR(255)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertArrayHasKey('full_name', $ztdRows[0]);
        $this->assertSame('Alice', $ztdRows[0]['full_name']);
    }

    public function testAlterTableChangeColumnRenamesAndModifiesType(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, age SMALLINT)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, age) VALUES (1, 25)");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` CHANGE COLUMN age user_age INT");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertArrayHasKey('user_age', $ztdRows[0]);
    }
}
