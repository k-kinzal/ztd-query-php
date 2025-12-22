<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateIgnoreTest extends MySqlIntegrationTestCase
{
    public function testUpdateIgnoreSyntaxIsSupported(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $affected = $this->ztdPdo->exec("UPDATE IGNORE `{$table}` SET name = 'Updated' WHERE id = 1");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertSame('Updated', $ztdRows[0]['name']);
    }
}
