<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Drop;

use Tests\Integration\MySqlIntegrationTestCase;

final class DropTableIfExistsTest extends MySqlIntegrationTestCase
{
    public function testDropTableIfExistsDoesNotErrorOnMissing(): void
    {
        $table = $this->uniqueTableName('nonexistent');

        $result = $this->ztdPdo->exec("DROP TABLE IF EXISTS `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testDropTableIfExistsRemovesExisting(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("DROP TABLE IF EXISTS `{$table}`");

        $this->expectException(\RuntimeException::class);
        $this->ztdPdo->query("SELECT * FROM `{$table}`");
    }
}
