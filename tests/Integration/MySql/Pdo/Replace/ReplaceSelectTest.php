<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Replace;

use Tests\Integration\MySqlIntegrationTestCase;

final class ReplaceSelectTest extends MySqlIntegrationTestCase
{
    public function testReplaceSelectInsertsFromQuery(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->ztdPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("CREATE TABLE `{$target}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdPdo->exec("REPLACE INTO `{$target}` SELECT * FROM `{$source}` WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
    }
}
