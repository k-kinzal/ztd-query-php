<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Replace;

use Tests\Integration\MySqlIntegrationTestCase;

final class ReplaceMultiRowTest extends MySqlIntegrationTestCase
{
    public function testReplaceMultipleRowsStoresInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("REPLACE INTO `{$table}` (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(3, $ztdRows);
    }

    public function testReplaceMultipleRowsReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $affected = $this->ztdPdo->exec("REPLACE INTO `{$table}` (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

        $this->assertSame(2, $affected);
    }

    public function testReplaceMultipleRowsWithSomeExisting(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'OldAlice'), (3, 'OldCharlie')");

        $this->ztdPdo->exec("REPLACE INTO `{$table}` (id, name) VALUES (1, 'NewAlice'), (2, 'Bob'), (3, 'NewCharlie')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(3, $ztdRows);
        $this->assertSame('NewAlice', $ztdRows[0]['name']);
        $this->assertSame('Bob', $ztdRows[1]['name']);
        $this->assertSame('NewCharlie', $ztdRows[2]['name']);
    }
}
