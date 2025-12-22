<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;

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

        // MySQL 8.0.16+ supports CHECK constraints
        $this->rawPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY,
            price DECIMAL(10,2),
            CONSTRAINT chk_price CHECK (price > 0)
        )");

        // This would violate the CHECK constraint (price = -100)
        // But ZTD does not validate CHECK constraints, so it should succeed
        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, price) VALUES (1, -100)");

        // INSERT should succeed in ZTD (no CHECK validation)
        $this->assertSame(1, $affected);

        // Verify the row is stored in shadow
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
        // Insert via ztdPdo so the row exists in shadow
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 100)");

        // This would violate the CHECK constraint (setting price to negative)
        // But ZTD does not validate CHECK constraints
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

        // Valid value that satisfies CHECK constraint
        $affected = $this->ztdPdo->exec("INSERT INTO `{$table}` (id, price) VALUES (1, 100)");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertEquals(100, $ztdRows[0]['price']);
    }
}
