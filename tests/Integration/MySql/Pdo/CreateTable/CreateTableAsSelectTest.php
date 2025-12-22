<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class CreateTableAsSelectTest extends MySqlIntegrationTestCase
{
    public function testCreateTableAsSelectCopiesData(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice', 100), (2, 'Bob', 200)");

        $this->ztdPdo->exec("CREATE TABLE `{$target}` AS SELECT id, name FROM `{$source}` WHERE score > 50");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
        $this->assertSame('Bob', $ztdRows[1]['name']);

        $this->assertArrayNotHasKey('score', $ztdRows[0]);
    }

    public function testCreateTableAsSelectWithWhereClause(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255), active TINYINT)");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice', 1), (2, 'Bob', 0), (3, 'Charlie', 1)");

        $this->ztdPdo->exec("CREATE TABLE `{$target}` AS SELECT * FROM `{$source}` WHERE active = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
        $this->assertSame('Charlie', $ztdRows[1]['name']);
    }
}
