<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;

/**
 * Tests for CHECK constraint handling.
 *
 * According to the specification, CHECK constraint evaluation is out of scope
 * because it requires SQL expression interpretation.
 *
 * ZTD does NOT validate CHECK constraints. Violations will not be detected
 * during virtual INSERT/UPDATE operations. This test documents this behavior.
 */
final class CheckConstraintViolationTest extends MySqlIntegrationTestCase
{
    public function testCheckConstraintViolationNotDetectedByZtd(): void
    {
        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY,
            price DECIMAL(10,2),
            CONSTRAINT chk_price CHECK (price > 0)
        )");

        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, price) VALUES (1, -100)");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertEquals(-100, $ztdRows[0]['price']);
    }

    public function testCheckConstraintViolationNotDetectedOnUpdate(): void
    {
        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY,
            price DECIMAL(10,2),
            CONSTRAINT chk_price CHECK (price > 0)
        )");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 100)");

        $affected = $this->ztdPdo->exec("UPDATE `{$table}` SET price = -50 WHERE id = 1");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertEquals(-50, $ztdRows[0]['price']);
    }

    public function testValidCheckConstraintValueSucceeds(): void
    {
        $table = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY,
            price DECIMAL(10,2),
            CONSTRAINT chk_price CHECK (price > 0)
        )");

        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, price) VALUES (1, 100)");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertEquals(100, $ztdRows[0]['price']);
    }
}
