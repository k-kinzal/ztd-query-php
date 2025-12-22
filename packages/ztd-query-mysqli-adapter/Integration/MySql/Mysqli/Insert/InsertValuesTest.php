<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Mysqli\Insert;

use Tests\Integration\MysqliIntegrationTestCase;

final class InsertValuesTest extends MysqliIntegrationTestCase
{
    public function testInsertSingleRowStoresInShadow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdMysqli->query("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }

    public function testInsertReturnsAffectedRowCount(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdMysqli->query("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->assertSame(1, $this->lastZtdAffectedRows());
    }

    public function testInsertWithPreparedStatement(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $stmt = $this->ztdMysqli->prepare("INSERT INTO `{$table}` (id, name) VALUES (?, ?)");
        $this->assertNotFalse($stmt);

        $id = 1;
        $name = 'Alice';
        $stmt->bind_param('is', $id, $name);
        $this->assertTrue($stmt->execute());

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rawRows);
    }
}
