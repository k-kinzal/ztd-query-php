<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class CreateTableLikeTest extends MySqlIntegrationTestCase
{
    public function testCreateTableLikeCopiesStructure(): void
    {
        $source = $this->uniqueTableName('source');
        $target = $this->uniqueTableName('target');

        $this->rawPdo->exec("CREATE TABLE `{$source}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->rawPdo->exec("INSERT INTO `{$source}` VALUES (1, 'Alice', 100)");

        $this->ztdPdo->exec("CREATE TABLE `{$target}` LIKE `{$source}`");

        $this->ztdPdo->exec("INSERT INTO `{$target}` VALUES (2, 'Bob', 200)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$target}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);
    }
}
