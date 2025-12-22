<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertSetTest extends MySqlIntegrationTestCase
{
    public function testInsertSetSyntaxStoresInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` SET id = 1, name = 'Alice', age = 30");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
        $this->assertSame(30, $ztdRows[0]['age']);
    }
}
