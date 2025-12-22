<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertSelectTest extends MySqlIntegrationTestCase
{
    public function testInsertSelectStoresInShadow(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdPdo->exec("INSERT INTO `{$target}` (id, name) SELECT id, name FROM `{$source}` WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
    }

    public function testInsertSelectWithColumnList(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice', 30)");

        $this->ztdPdo->exec("INSERT INTO `{$target}` (id, name) SELECT id, name FROM `{$source}`");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}`");
        $this->assertCount(1, $ztdRows);
    }
}
