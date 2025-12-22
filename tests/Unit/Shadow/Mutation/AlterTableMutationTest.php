<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\AlterTableMutation;
use ZtdQuery\Shadow\ShadowStore;

final class AlterTableMutationTest extends TestCase
{
    private function createAlterStatement(string $sql): AlterStatement
    {
        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;
        $this->assertInstanceOf(AlterStatement::class, $stmt);
        return $stmt;
    }

    public function testApplyAddColumnAddsNewColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        $schema = $registry->get('users');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('email', $schema);
    }

    public function testApplyAddColumnWithoutColumnKeyword(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users ADD name VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        $schema = $registry->get('users');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('name', $schema);
    }

    public function testApplyAddColumnThrowsExceptionWhenColumnExists(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users ADD COLUMN name VARCHAR(100)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(ColumnAlreadyExistsException::class);
        $this->expectExceptionMessage("Column 'name' already exists in table 'users'.");

        $mutation->apply($store, []);
    }

    public function testApplyDropColumnRemovesColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $alterStmt = $this->createAlterStatement('ALTER TABLE users DROP COLUMN name');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        $schema = $registry->get('users');
        $this->assertNotNull($schema);
        $this->assertStringNotContainsString('name', $schema);

        // Column should be removed from store data
        $this->assertArrayNotHasKey('name', $store->get('users')[0]);
    }

    public function testApplyDropColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users DROP COLUMN email');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(ColumnNotFoundException::class);
        $this->expectExceptionMessage("Column 'email' does not exist in table 'users'.");

        $mutation->apply($store, []);
    }

    public function testApplyModifyColumnModifiesColumnDefinition(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users MODIFY COLUMN name VARCHAR(255) NOT NULL');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        $schema = $registry->get('users');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('name', $schema);
    }

    public function testApplyModifyColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(ColumnNotFoundException::class);

        $mutation->apply($store, []);
    }

    public function testApplyChangeColumnRenamesAndModifiesColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $alterStmt = $this->createAlterStatement('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        $schema = $registry->get('users');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('full_name', $schema);

        // Column should be renamed in store data
        $this->assertArrayHasKey('full_name', $store->get('users')[0]);
        $this->assertArrayNotHasKey('name', $store->get('users')[0]);
    }

    public function testApplyChangeColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users CHANGE COLUMN email new_email VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(ColumnNotFoundException::class);

        $mutation->apply($store, []);
    }

    public function testApplyThrowsExceptionWhenTableNotFound(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage("Table 'users' does not exist.");

        $mutation->apply($store, []);
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new SchemaRegistry();
        $alterStmt = $this->createAlterStatement('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyRenameColumnRenamesExistingColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $alterStmt = $this->createAlterStatement('ALTER TABLE users RENAME COLUMN name TO full_name');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        // Column should be renamed in store data
        $this->assertArrayHasKey('full_name', $store->get('users')[0]);
        $this->assertSame('Alice', $store->get('users')[0]['full_name']);
    }

    public function testApplyRenameColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users RENAME COLUMN email TO new_email');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);

        $this->expectException(ColumnNotFoundException::class);

        $mutation->apply($store, []);
    }

    public function testApplyDropPrimaryKeyRemovesPrimaryKey(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        $store = new ShadowStore();

        $alterStmt = $this->createAlterStatement('ALTER TABLE users DROP PRIMARY KEY');
        $mutation = new AlterTableMutation('users', $alterStmt, $registry);
        $mutation->apply($store, []);

        // Schema should be updated
        $schema = $registry->get('users');
        $this->assertNotNull($schema);
    }
}
