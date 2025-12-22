<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Mysqli\Delete;

use Tests\Integration\MysqliIntegrationTestCase;

final class DeleteWhereTest extends MysqliIntegrationTestCase
{
    public function testDeleteSingleRowFromShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdMysqli->query("DELETE FROM `{$table}` WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);

        // Raw should have 0 rows (INSERTs were shadowed, not executed on real DB)
        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(0, $rawRows);
    }

    public function testDeleteReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdMysqli->query("DELETE FROM `{$table}` WHERE id = 1");

        $this->assertSame(1, $this->lastZtdAffectedRows());
    }

    public function testDeleteWithPreparedStatement(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $stmt = $this->ztdMysqli->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $this->assertNotFalse($stmt);

        $id = 1;
        $stmt->bind_param('i', $id);
        $this->assertTrue($stmt->execute());

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(1, $ztdRows);
    }
}
