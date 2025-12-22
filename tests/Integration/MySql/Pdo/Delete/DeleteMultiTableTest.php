<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteMultiTableTest extends MySqlIntegrationTestCase
{
    public function testDeleteFromPrimaryTable(): void
    {
        $users = $this->uniqueTableName('users');
        $profiles = $this->uniqueTableName('profiles');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$profiles}` (user_id INT PRIMARY KEY, bio TEXT)");
        $this->ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->ztdPdo->exec("INSERT INTO `{$profiles}` VALUES (1, 'Alice bio'), (2, 'Bob bio')");

        $this->ztdPdo->exec("DELETE u FROM `{$users}` u INNER JOIN `{$profiles}` p ON u.id = p.user_id WHERE u.id = 1");

        $ztdUsers = $this->ztdQuery("SELECT * FROM `{$users}`");
        $this->assertCount(1, $ztdUsers);
        $this->assertSame('Bob', $ztdUsers[0]['name']);
    }
}
