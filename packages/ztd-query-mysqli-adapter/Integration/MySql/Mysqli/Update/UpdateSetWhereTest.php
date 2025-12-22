<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Mysqli\Update;

use Tests\Integration\MysqliIntegrationTestCase;

final class UpdateSetWhereTest extends MysqliIntegrationTestCase
{
    public function testUpdateSingleRowInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdMysqli->query("UPDATE `{$table}` SET name = 'Updated' WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertSame('Updated', $ztdRows[0]['name']);
        $this->assertSame('Bob', $ztdRows[1]['name']);

        // Raw should have 0 rows (INSERTs were shadowed)
        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(0, $rawRows);
    }

    public function testUpdateReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->ztdMysqli->query("UPDATE `{$table}` SET name = 'Updated' WHERE id = 1");

        $this->assertSame(1, $this->lastZtdAffectedRows());
    }

    public function testUpdateWithPreparedStatement(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        // Insert via ZTD so rows are in shadow
        $this->ztdMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $stmt = $this->ztdMysqli->prepare("UPDATE `{$table}` SET name = ? WHERE id = ?");
        $this->assertNotFalse($stmt);

        $name = 'Updated';
        $id = 1;
        $stmt->bind_param('si', $name, $id);
        $this->assertTrue($stmt->execute());

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertSame('Updated', $ztdRows[0]['name']);
    }
}
