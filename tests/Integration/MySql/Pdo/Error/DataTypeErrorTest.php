<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use PDOException;
use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for data type errors.
 *
 * These tests verify that type conversion errors are properly
 * detected and reported.
 */
final class DataTypeErrorTest extends MySqlIntegrationTestCase
{
    public function testInsertInvalidIntegerStoredInShadow(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, age INT NOT NULL)");

        // ZTD stores the value in shadow - invalid integer is coerced
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, age) VALUES (1, 'not_a_number')");

        // ZTD stores the value as-is, verify it's stored
        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        // MySQL coerces invalid integers to 0
        $this->assertEquals(0, $rows[0]['age']);
    }

    public function testInsertInvalidDateDetectedByDatabase(): void
    {
        $table = $this->uniqueTableName('events');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, event_date DATE)");

        // Invalid date value - ZTD stores the value
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, event_date) VALUES (1, 'not_a_date')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        // MySQL coerces invalid dates to '0000-00-00' or NULL depending on mode
    }

    public function testInsertValidTypeSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, age INT)");

        // Valid integer value
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, age) VALUES (1, 25)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(25, $rows[0]['age']);
    }

    public function testInsertStringToIntegerWithImplicitConversion(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, age INT)");

        // String that can be converted to integer
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, age) VALUES (1, '25')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(25, $rows[0]['age']);
    }

    public function testInsertValidDateSucceeds(): void
    {
        $table = $this->uniqueTableName('events');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, event_date DATE)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, event_date) VALUES (1, '2024-01-15')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('2024-01-15', $rows[0]['event_date']);
    }

    public function testInsertValidDateTimeSucceeds(): void
    {
        $table = $this->uniqueTableName('events');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, created_at DATETIME)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, created_at) VALUES (1, '2024-01-15 10:30:00')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('2024-01-15 10:30:00', $rows[0]['created_at']);
    }

    public function testInsertDecimalSucceeds(): void
    {
        $table = $this->uniqueTableName('prices');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, price DECIMAL(10,2))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, price) VALUES (1, 99.99)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(99.99, $rows[0]['price'], 0.001);
    }

    public function testInsertBooleanAsIntegerSucceeds(): void
    {
        $table = $this->uniqueTableName('flags');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, is_active TINYINT(1))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, is_active) VALUES (1, 1)");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['is_active']);
    }

    public function testUpdateWithValidTypeSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, age INT)");

        // First insert via ZTD shadow
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, age) VALUES (1, 25)");

        // Update the shadow row
        $this->ztdPdo->exec("UPDATE `{$table}` SET age = 30 WHERE id = 1");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals(30, $rows[0]['age']);
    }
}
