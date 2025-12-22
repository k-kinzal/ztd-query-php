<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectUnionAllTest extends MySqlIntegrationTestCase
{
    public function testSelectUnionAllCombinesResultsWithDuplicates(): void
    {
        $table1 = $this->uniqueTableName('users1');
        $table2 = $this->uniqueTableName('users2');

        $this->rawPdo->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$table2}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table1}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$table2}` VALUES (3, 'Bob'), (4, 'Charlie')");

        $rawRows = $this->rawQuery("SELECT name FROM `{$table1}` UNION ALL SELECT name FROM `{$table2}` ORDER BY name");
        $ztdRows = $this->ztdQuery("SELECT name FROM `{$table1}` UNION ALL SELECT name FROM `{$table2}` ORDER BY name");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(4, $ztdRows); // Bob appears twice
    }
}
