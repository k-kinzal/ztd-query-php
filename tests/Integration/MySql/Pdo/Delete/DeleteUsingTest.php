<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteUsingTest extends MySqlIntegrationTestCase
{
    public function testDeleteWithJoinSyntax(): void
    {
        $users = $this->uniqueTableName('users');
        $blacklist = $this->uniqueTableName('blacklist');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$blacklist}` (user_id INT PRIMARY KEY)");
        $this->ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->ztdPdo->exec("INSERT INTO `{$blacklist}` VALUES (1)");

        $affected = $this->ztdPdo->exec("DELETE u FROM `{$users}` u INNER JOIN `{$blacklist}` b ON u.id = b.user_id");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$users}` ORDER BY id");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);
    }
}
