<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\MultipleStatement;

use Tests\Support\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * Tests for nextRowset() behavior with multiple statements.
 *
 * According to the specification, multiple statements are processed via
 * rewriteMultiple() and result sets are retrieved using nextRowset().
 *
 * Since multiple statements are currently Unsupported, these tests verify
 * that the behavior follows the Unsupported SQL configuration.
 */
final class NextRowsetTest extends MySqlIntegrationTestCase
{
    public function testNextRowsetWithMultipleSelectsThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $this->expectException(ZtdPdoException::class);

        $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1; SELECT * FROM `{$table}` WHERE id = 2");
    }

    public function testNextRowsetWithMultipleSelectsIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $result = $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1; SELECT * FROM `{$table}` WHERE id = 2");

        $this->assertFalse($result);
    }

    public function testSingleStatementNextRowsetReturnsFalse(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $stmt = $this->ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1");

        $this->assertNotFalse($stmt);
        $rows = $stmt->fetchAll();
        $this->assertCount(1, $rows);

        $hasNextRowset = $stmt->nextRowset();
        $this->assertFalse($hasNextRowset);
    }
}
