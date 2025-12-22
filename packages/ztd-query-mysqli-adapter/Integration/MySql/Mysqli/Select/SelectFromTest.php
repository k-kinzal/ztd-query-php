<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Mysqli\Select;

use Tests\Integration\MysqliIntegrationTestCase;

final class SelectFromTest extends MysqliIntegrationTestCase
{
    public function testSelectFromReturnsAllRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");

        $this->assertCount(2, $rows);
    }

    public function testSelectFromWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");

        // Use assertEquals for type-flexible comparison (mysqli_query vs mysqli_stmt may return different int types)
        $this->assertEquals($rawRows, $ztdRows);
    }

    public function testSelectSpecificColumnsFromTable(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), age INT)");
        $this->rawMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice', 30)");

        $rawRows = $this->rawQuery("SELECT name, age FROM `{$table}`");
        $ztdRows = $this->ztdQuery("SELECT name, age FROM `{$table}`");

        // Use assertEquals for type-flexible comparison (mysqli_query vs mysqli_stmt may return different int types)
        $this->assertEquals($rawRows, $ztdRows);
        $this->assertArrayNotHasKey('id', $ztdRows[0]);
    }

    public function testSelectWithPreparedStatement(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawMysqli->query("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawMysqli->query("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $stmt = $this->ztdMysqli->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $this->assertNotFalse($stmt);

        $id = 1;
        $stmt->bind_param('i', $id);
        $this->assertTrue($stmt->execute());

        $result = $stmt->get_result();
        $this->assertNotFalse($result);

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }
}
