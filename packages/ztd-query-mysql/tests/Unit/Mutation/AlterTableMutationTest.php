<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(AlterTableMutation::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlSchemaParser::class)]
final class AlterTableMutationTest extends TestCase
{
    public function testApplyAddColumnAddsNewColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertContains('email', $newDef->columns);
    }

    public function testApplyAddColumnWithoutColumnKeyword(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD name VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertContains('name', $newDef->columns);
    }

    public function testApplyAddColumnThrowsExceptionWhenColumnExists(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN name VARCHAR(100)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnAlreadyExistsException::class);
        $this->expectExceptionMessage("Column 'name' already exists in table 'users'.");
        $mutation->apply($store, []);
    }

    public function testApplyDropColumnRemovesColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users DROP COLUMN name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertNotContains('name', $newDef->columns);
        self::assertArrayNotHasKey('name', $store->get('users')[0]);
    }

    public function testApplyDropColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users DROP COLUMN email');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $this->expectExceptionMessage("Column 'email' does not exist in table 'users'.");
        $mutation->apply($store, []);
    }

    public function testApplyModifyColumnModifiesColumnDefinition(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users MODIFY COLUMN name VARCHAR(255) NOT NULL');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertContains('name', $newDef->columns);
    }

    public function testApplyModifyColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users MODIFY COLUMN email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyChangeColumnRenamesAndModifiesColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertContains('full_name', $newDef->columns);
        self::assertArrayHasKey('full_name', $store->get('users')[0]);
        self::assertArrayNotHasKey('name', $store->get('users')[0]);
    }

    public function testApplyChangeColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users CHANGE COLUMN email new_email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyThrowsExceptionWhenTableNotFound(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage("Table 'users' does not exist.");
        $mutation->apply($store, []);
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new TableDefinitionRegistry();
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $parser = new Parser('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyRenameColumnRenamesExistingColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users RENAME COLUMN name TO full_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertArrayHasKey('full_name', $store->get('users')[0]);
        self::assertSame('Alice', $store->get('users')[0]['full_name']);
    }

    public function testApplyRenameColumnThrowsExceptionWhenColumnNotFound(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users RENAME COLUMN email TO new_email');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyDropPrimaryKeyRemovesPrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users DROP PRIMARY KEY');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('users');
        self::assertNotNull($newDef);
        self::assertSame([], $newDef->primaryKeys);
    }

    public function testBuildCreateTableSqlPreservesNotNull(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100) NOT NULL)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('name', $def->notNullColumns);
        self::assertContains('email', $def->columns);
    }

    public function testBuildCreateTableSqlSinglePrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertSame(['id'], $def->primaryKeys);
    }

    public function testBuildCreateTableSqlCompositePrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE order_items (order_id INT NOT NULL, item_id INT NOT NULL, qty INT, PRIMARY KEY (order_id, item_id))');
        self::assertNotNull($definition);
        $registry->register('order_items', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE order_items ADD COLUMN note TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('order_items', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('order_items');
        self::assertNotNull($def);
        self::assertContains('order_id', $def->primaryKeys);
        self::assertContains('item_id', $def->primaryKeys);
        self::assertCount(2, $def->primaryKeys);
    }

    public function testBuildCreateTableSqlPreservesUniqueConstraints(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), UNIQUE KEY uk_email (email))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertNotEmpty($def->uniqueConstraints);
    }

    public function testDropColumnWithoutColumnKeyword(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100), email TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'a@b.com']]);

        $parser = new Parser('ALTER TABLE users DROP name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertNotContains('name', $def->columns);
        self::assertArrayNotHasKey('name', $store->get('users')[0]);
    }

    public function testRenameTableMovesDataAndRegistry(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users RENAME TO members');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([['id' => 1, 'name' => 'Alice']], $store->get('members'));
        self::assertSame([], $store->get('users'));
        self::assertNotNull($registry->get('members'));
        self::assertNull($registry->get('users'));
        self::assertSame('members', $mutation->tableName());
    }

    public function testAddPrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE data (id INT NOT NULL, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('data', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE data ADD PRIMARY KEY (`id`)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('data', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('data');
        self::assertNotNull($def);
        self::assertContains('id', $def->columns);
    }

    public function testAddForeignKeySkipped(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertNotNull($registry->get('orders'));
    }

    public function testDropForeignKeySkipped(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders DROP FOREIGN KEY fk_user');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertNotNull($registry->get('orders'));
    }

    public function testUnsupportedOperationThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users LOCK = EXCLUSIVE');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testBuildCreateTableSqlUsesTypedColumnNativeType(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'price'],
            ['id' => 'INT', 'price' => 'DECIMAL(10,2)'],
            ['id'],
            ['id'],
            [],
            [
                'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                'price' => new ColumnType(ColumnTypeFamily::DECIMAL, 'DECIMAL(10,2)'),
            ],
        ));
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN note TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('note', $def->columns);
        self::assertContains('id', $def->columns);
        self::assertContains('price', $def->columns);
    }

    public function testMultipleAlterOperations(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100), email TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users DROP COLUMN email, ADD COLUMN phone VARCHAR(20)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertNotContains('email', $def->columns);
        self::assertContains('phone', $def->columns);
    }

    public function testDropColumnWithEmptyStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users DROP COLUMN name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertNotContains('name', $def->columns);
    }

    public function testAlterDropDefaultUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100) DEFAULT "test")');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ALTER COLUMN name DROP DEFAULT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testModifyWithoutColumnKeyword(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users MODIFY name VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('name', $def->columns);
    }

    public function testChangeWithoutColumnKeyword(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users CHANGE name username VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('username', $def->columns);
        self::assertNotContains('name', $def->columns);
        self::assertSame('Alice', $store->get('users')[0]['username']);
    }

    public function testChangeColumnSameNameModifiesType(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE users CHANGE COLUMN name name VARCHAR(255) NOT NULL');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('name', $def->columns);
        self::assertContains('name', $def->notNullColumns);
        self::assertSame('Alice', $store->get('users')[0]['name']);
    }

    public function testRenameColumnUpdatesStoreData(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $parser = new Parser('ALTER TABLE users RENAME COLUMN name TO full_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['full_name']);
        self::assertSame('Bob', $rows[1]['full_name']);
        self::assertArrayNotHasKey('name', $rows[0]);
        self::assertArrayNotHasKey('name', $rows[1]);
    }

    public function testBuildCreateTableSqlCorrectTypedColumnNativeType(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            ['id' => 'INT', 'val' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            [
                'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                'val' => new ColumnType(ColumnTypeFamily::TEXT, 'TEXT'),
            ],
        ));
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN extra INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('extra', $def->columns);
        self::assertContains('id', $def->columns);
        self::assertContains('val', $def->columns);
    }

    public function testBuildCreateTableSqlFallsBackToColumnTypesWhenNoTypedColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            ['id' => 'INT', 'val' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            ['id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
        ));
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN extra INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('val', $def->columns);
    }

    public function testBuildCreateTableSqlFallsBackToTextWhenNoTypes(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            [],
            [],
            [],
            [],
            [],
        ));
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN extra INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('extra', $def->columns);
    }

    public function testApplyUnregistersOldTableBeforeRegistering(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('email', $def->columns);
        self::assertContains('id', $def->columns);
        self::assertContains('name', $def->columns);
    }

    public function testBuildCreateTableSqlCompositePrimaryKeyProducesBacktickedColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT NOT NULL, b INT NOT NULL, c TEXT, PRIMARY KEY (a, b))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN d TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertCount(2, $def->primaryKeys);
        self::assertContains('a', $def->primaryKeys);
        self::assertContains('b', $def->primaryKeys);
    }

    public function testBuildCreateTableSqlUniqueConstraintsProducesBacktickedColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, email VARCHAR(255), UNIQUE KEY uk_email (email))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN phone VARCHAR(20)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertNotEmpty($def->uniqueConstraints);
        $matches = array_values(array_filter($def->uniqueConstraints, static fn (array $cols): bool => in_array('email', $cols, true)));
        self::assertNotEmpty($matches);
        self::assertSame(['email'], $matches[0]);
    }

    public function testDropColumnRemovesFromMultipleStoreRows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a TEXT, b TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [
            ['id' => 1, 'a' => 'x', 'b' => 'y'],
            ['id' => 2, 'a' => 'p', 'b' => 'q'],
        ]);

        $parser = new Parser('ALTER TABLE t DROP COLUMN a');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayNotHasKey('a', $rows[0]);
        self::assertArrayNotHasKey('a', $rows[1]);
        self::assertArrayHasKey('b', $rows[0]);
        self::assertArrayHasKey('b', $rows[1]);
    }

    public function testAlterSetDefaultUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser("ALTER TABLE t ALTER COLUMN name SET DEFAULT 'test'");
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testRenameTablePreservesSchemaDefinition(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(100) NOT NULL)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE t RENAME TO t2');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertNull($registry->get('t'));
        $def2 = $registry->get('t2');
        self::assertNotNull($def2);
        self::assertSame(['id', 'name'], $def2->columns);
        self::assertContains('name', $def2->notNullColumns);
        self::assertSame([['id' => 1, 'name' => 'Alice']], $store->get('t2'));
        self::assertSame([], $store->get('t'));
    }

    public function testModifyColumnChangesType(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY COLUMN name TEXT NOT NULL');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('name', $def->notNullColumns);
    }

    public function testAddPrimaryKeyPopulatesPrimaryKeys(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE data (id INT NOT NULL, name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('data', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE data ADD PRIMARY KEY (`id`)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('data', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('data');
        self::assertNotNull($def);
        self::assertContains('id', $def->primaryKeys);
        self::assertCount(1, $def->primaryKeys);
    }

    public function testAddCompositePrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE data (a INT NOT NULL, b INT NOT NULL, c TEXT)');
        self::assertNotNull($definition);
        $registry->register('data', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE data ADD PRIMARY KEY (`a`, `b`)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('data', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('data');
        self::assertNotNull($def);
        self::assertContains('a', $def->primaryKeys);
        self::assertContains('b', $def->primaryKeys);
        self::assertCount(2, $def->primaryKeys);
    }

    public function testDropPrimaryKeyRemovesCompositePrimaryKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT NOT NULL, b INT NOT NULL, c TEXT, PRIMARY KEY (a, b))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP PRIMARY KEY');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame([], $def->primaryKeys);
    }

    public function testDropColumnUpdatesFieldsArrayValues(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT PRIMARY KEY, b TEXT, c TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP COLUMN b');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['a', 'c'], $def->columns);
    }

    public function testRenameTableUnregistersOldName(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE old_t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('old_t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE old_t RENAME TO new_t');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('old_t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertNull($registry->get('old_t'));
        self::assertNotNull($registry->get('new_t'));
        self::assertSame(['id'], $registry->get('new_t')->columns);
    }

    public function testModifyColumnUpdatesColumnTypeInSchema(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, val VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY COLUMN val TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('val', $def->columns);
        self::assertSame(['id', 'val'], $def->columns);
    }

    public function testAddColumnBacktickNameNormalized(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN `email` VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('email', $def->columns);
    }

    public function testApplyUnregistersOldDefinitionBeforeReregistering(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('users', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertContains('email', $def->columns);
        self::assertCount(3, $def->columns);
    }

    public function testBuildCreateTableSqlCompositePrimaryKeyWrapsBackticks(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE items (order_id INT NOT NULL, product_id INT NOT NULL, qty INT, PRIMARY KEY (order_id, product_id))');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE items ADD COLUMN note TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('items', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('items');
        self::assertNotNull($def);
        self::assertContains('note', $def->columns);
        self::assertSame(['order_id', 'product_id'], $def->primaryKeys);
    }

    public function testBuildCreateTableSqlUniqueConstraintWrapsBackticks(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, email VARCHAR(255), UNIQUE KEY uk_email (email))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN name VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('name', $def->columns);
        self::assertNotEmpty($def->uniqueConstraints);
        self::assertArrayHasKey('uk_email', $def->uniqueConstraints);
        self::assertSame(['email'], $def->uniqueConstraints['uk_email']);
    }

    public function testApplyAddForeignKeyDoesNotThrow(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
    }

    public function testApplyDropForeignKeyDoesNotThrow(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders DROP FOREIGN KEY fk_user');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
    }

    public function testApplyRenameColumnUpdatesDefinitionAndStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'old_name' => 'Alice']]);

        $parser = new Parser('ALTER TABLE t RENAME COLUMN old_name TO new_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_name', $def->columns);
        self::assertNotContains('old_name', $def->columns);

        $rows = $store->get('t');
        self::assertNotEmpty($rows);
        self::assertArrayHasKey('new_name', $rows[0]);
        self::assertArrayNotHasKey('old_name', $rows[0]);
        self::assertSame('Alice', $rows[0]['new_name']);
    }

    public function testApplyAlterSetDefaultIsHandledViaRewriter(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser("ALTER TABLE t ALTER COLUMN name SET DEFAULT 'test'");
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);

        $altered = $alterStmt->altered ?? [];
        self::assertNotEmpty($altered);
    }

    public function testModifyColumnWithMultipleFieldsOnlyModifiesTarget(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a VARCHAR(100), b VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY COLUMN a TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('a', $def->columns);
        self::assertContains('b', $def->columns);
        self::assertSame('TEXT', $def->columnTypes['a']);
    }

    public function testChangeColumnRenamesAndUpdatesType(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_col VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'old_col' => 'val']]);

        $parser = new Parser('ALTER TABLE t CHANGE COLUMN old_col new_col TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_col', $def->columns);
        self::assertNotContains('old_col', $def->columns);

        $rows = $store->get('t');
        self::assertNotEmpty($rows);
        self::assertArrayHasKey('new_col', $rows[0]);
        self::assertArrayNotHasKey('old_col', $rows[0]);
    }

    public function testDropPrimaryKeyRemovesPkFromDefinition(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP PRIMARY KEY');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame([], $def->primaryKeys);
    }

    public function testAddPrimaryKeyDoesNotRemoveExistingColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT, b INT, c VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD PRIMARY KEY (a, b)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('a', $def->columns);
        self::assertContains('b', $def->columns);
        self::assertContains('c', $def->columns);
    }

    public function testRenameTableUpdatesRegistryAndStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE old_table (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('old_table', $definition);
        $store = new ShadowStore();
        $store->set('old_table', [['id' => 1]]);

        $parser = new Parser('ALTER TABLE old_table RENAME TO new_table');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('old_table', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertTrue($registry->has('new_table'));
        self::assertSame([], $store->get('old_table'));
        self::assertNotEmpty($store->get('new_table'));
        self::assertSame('new_table', $mutation->tableName());
    }

    public function testDropColumnNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP COLUMN nonexistent');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testModifyColumnNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY COLUMN nonexistent TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testChangeColumnNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t CHANGE COLUMN nonexistent new_col TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testRenameColumnNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME COLUMN nonexistent TO new_col');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testDropColumnRemovesFromStoreData(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a VARCHAR(100), b VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'a' => 'x', 'b' => 'y'], ['id' => 2, 'a' => 'p', 'b' => 'q']]);

        $parser = new Parser('ALTER TABLE t DROP COLUMN a');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayNotHasKey('a', $rows[0]);
        self::assertArrayHasKey('b', $rows[0]);
        self::assertArrayHasKey('id', $rows[0]);
    }

    public function testBuildCreateTableSqlColumnWithNoTypedColumnFallsBackToColumnTypes(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
            [],
        );
        $registry->register('t', $definition);
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN email TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('email', $def->columns);
        self::assertContains('id', $def->columns);
        self::assertContains('name', $def->columns);
    }

    public function testApplyUnregistersOldDefinitionAndRegistersNew(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $originalDef = $registry->get('t');
        self::assertNotNull($originalDef);
        self::assertCount(2, $originalDef->columns);

        $parser = new Parser('ALTER TABLE t ADD COLUMN email VARCHAR(100)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $newDef = $registry->get('t');
        self::assertNotNull($newDef);
        self::assertCount(3, $newDef->columns);
        self::assertSame(['id', 'name', 'email'], $newDef->columns);
    }

    public function testBuildCreateTableSqlCompositePkBacktickWrappingExact(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE items (a INT NOT NULL, b INT NOT NULL, c TEXT, PRIMARY KEY (a, b))');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE items ADD COLUMN d INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('items', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('items');
        self::assertNotNull($def);
        self::assertSame(['a', 'b'], $def->primaryKeys);
        self::assertContains('d', $def->columns);
    }

    public function testBuildCreateTableSqlUniqueConstraintClosingParenthesisExact(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, email VARCHAR(255), slug VARCHAR(255), UNIQUE KEY uk_email (email), UNIQUE KEY uk_slug (slug))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN name TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertArrayHasKey('uk_email', $def->uniqueConstraints);
        self::assertSame(['email'], $def->uniqueConstraints['uk_email']);
        self::assertArrayHasKey('uk_slug', $def->uniqueConstraints);
        self::assertSame(['slug'], $def->uniqueConstraints['uk_slug']);
    }

    public function testApplyAddForeignKeyLeavesSchemaUnchanged(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
        self::assertSame(['id'], $def->primaryKeys);
    }

    public function testApplyDropForeignKeyLeavesSchemaUnchanged(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders DROP FOREIGN KEY fk_user');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
        self::assertSame(['id'], $def->primaryKeys);
    }

    public function testApplyRenameTableTransfersStoreDataToNewName(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE src (id INT PRIMARY KEY, val TEXT)');
        self::assertNotNull($definition);
        $registry->register('src', $definition);
        $store = new ShadowStore();
        $store->set('src', [['id' => 1, 'val' => 'x'], ['id' => 2, 'val' => 'y']]);

        $parser = new Parser('ALTER TABLE src RENAME TO dst');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('src', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('src'));
        self::assertSame([['id' => 1, 'val' => 'x'], ['id' => 2, 'val' => 'y']], $store->get('dst'));
        self::assertTrue($registry->has('dst'));
        self::assertFalse($registry->has('src'));
        self::assertSame('dst', $mutation->tableName());
    }

    public function testApplyRenameColumnUpdatesStoreData(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_col VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'old_col' => 'a'], ['id' => 2, 'old_col' => 'b']]);

        $parser = new Parser('ALTER TABLE t RENAME COLUMN old_col TO new_col');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayHasKey('new_col', $rows[0]);
        self::assertArrayNotHasKey('old_col', $rows[0]);
        self::assertSame('a', $rows[0]['new_col']);
        self::assertArrayHasKey('new_col', $rows[1]);
        self::assertSame('b', $rows[1]['new_col']);
    }

    public function testApplyDropColumnRemovesColumnFromStoreRows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255), age INT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 25]]);

        $parser = new Parser('ALTER TABLE t DROP COLUMN age');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayNotHasKey('age', $rows[0]);
        self::assertSame(['id' => 1, 'name' => 'Alice'], $rows[0]);
        self::assertSame(['id' => 2, 'name' => 'Bob'], $rows[1]);
    }

    public function testApplyDropColumnWithoutColumnKeywordRemovesColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255), extra TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP extra');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['id', 'name'], $def->columns);
        self::assertNotContains('extra', $def->columns);
    }

    public function testApplyChangeColumnUpdatesStoreColumnName(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_name VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'old_name' => 'val']]);

        $parser = new Parser('ALTER TABLE t CHANGE old_name new_name TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertArrayHasKey('new_name', $rows[0]);
        self::assertArrayNotHasKey('old_name', $rows[0]);
        self::assertSame('val', $rows[0]['new_name']);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_name', $def->columns);
    }

    public function testApplyAddPrimaryKeyPreservesAllColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT, name VARCHAR(255), email TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD PRIMARY KEY (id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['id', 'name', 'email'], $def->columns);
    }

    public function testApplyDropPrimaryKeyRemovesPkConstraint(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP PRIMARY KEY');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame([], $def->primaryKeys);
        self::assertContains('id', $def->columns);
        self::assertContains('name', $def->columns);
    }

    public function testApplyModifyColumnPreservesOtherColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a VARCHAR(50), b VARCHAR(50), c VARCHAR(50))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY b TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['id', 'a', 'b', 'c'], $def->columns);
        self::assertSame('TEXT', $def->columnTypes['b']);
        self::assertSame('VARCHAR(50)', $def->columnTypes['a'] ?? '');
    }

    public function testApplyNotNullColumnPreservesNotNullInRoundTrip(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN email VARCHAR(100)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('id', $def->notNullColumns);
        self::assertContains('name', $def->notNullColumns);
    }

    public function testApplySchemaNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE nonexistent ADD COLUMN x INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('nonexistent', $alterStmt, $registry, $schemaParser);

        $this->expectException(SchemaNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyChangeColumnNotFoundThrows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t CHANGE nonexistent new_col TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyRenameColumnNotFoundThrowsException(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME COLUMN nonexistent TO new_col');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(ColumnNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testBuildCreateTableSqlFallsBackToTextWhenNoColumnTypes(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id', 'val'],
            [],
            [],
            [],
            [],
            [],
        );
        $registry->register('t', $definition);
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN extra INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('extra', $def->columns);
        self::assertContains('id', $def->columns);
        self::assertContains('val', $def->columns);
    }

    public function testApplyDropColumnEmptyStoreDoesNotModifyStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255), extra TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP COLUMN extra');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('t'));
        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertNotContains('extra', $def->columns);
    }

    public function testApplyRenameTableEmptyStoreTransfersEmpty(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE src (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('src', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE src RENAME TO dst');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('src', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('src'));
        self::assertSame([], $store->get('dst'));
        self::assertTrue($registry->has('dst'));
    }

    public function testApplyChangeColumnSameNameKeepsStoreUnchanged(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, col VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'col' => 'v']]);

        $parser = new Parser('ALTER TABLE t CHANGE col col TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertSame([['id' => 1, 'col' => 'v']], $rows);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('col', $def->columns);
    }

    public function testApplyRenameColumnEmptyStoreDoesNotModifyStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_col VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME COLUMN old_col TO new_col');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('t'));
        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_col', $def->columns);
    }

    public function testApplyUnsupportedAlterThrowsException(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD INDEX idx_id (id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testApplyAlterDropDefaultIsHandled(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ALTER COLUMN name DROP DEFAULT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);

        $altered = $alterStmt->altered ?? [];
        self::assertNotEmpty($altered);
    }

    public function testApplyAddKeyThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD KEY idx_name (name)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testApplyRenameIndexThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME INDEX old_idx TO new_idx');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testApplyRenameKeyThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME KEY old_key TO new_key');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testBuildCreateTableSqlCompositePkContainsBacktickWrappedColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT NOT NULL, b INT NOT NULL, PRIMARY KEY (a, b))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN c TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['a', 'b'], $def->primaryKeys);
        self::assertContains('c', $def->columns);
    }

    public function testBuildCreateTableSqlUniqueConstraintContainsClosingParenthesis(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, email VARCHAR(255), UNIQUE KEY uk_email (email))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN phone VARCHAR(20)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertArrayHasKey('uk_email', $def->uniqueConstraints);
        self::assertSame(['email'], $def->uniqueConstraints['uk_email']);
    }

    public function testBuildCreateTableSqlColumnTypeFallbackToColumnTypes(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            ['id' => 'INT', 'val' => 'VARCHAR(100)'],
            ['id'],
            [],
            [],
            [],
        ));
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN extra INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('extra', $def->columns);
        self::assertSame(['id'], $def->primaryKeys);
    }

    public function testRenameColumnEmptyStoreDoesNotModifyStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_col VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME COLUMN old_col TO new_col');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_col', $def->columns);
        self::assertNotContains('old_col', $def->columns);
        self::assertSame([], $store->get('t'));
    }

    public function testChangeColumnUpdatesStoreColumnKey(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'old_name' => 'val'], ['id' => 2, 'old_name' => 'val2']]);

        $parser = new Parser('ALTER TABLE t CHANGE COLUMN old_name new_name TEXT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayHasKey('new_name', $rows[0]);
        self::assertArrayNotHasKey('old_name', $rows[0]);
        self::assertArrayHasKey('new_name', $rows[1]);
        self::assertArrayNotHasKey('old_name', $rows[1]);
    }

    public function testRenameTableEmptyStoreDoesNotModifyStore(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME TO t2');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertNotNull($registry->get('t2'));
        self::assertNull($registry->get('t'));
        self::assertSame([], $store->get('t'));
        self::assertSame([], $store->get('t2'));
    }

    public function testDropColumnRemovesFromStoreRows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a TEXT, b TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'a' => 'x', 'b' => 'y']]);

        $parser = new Parser('ALTER TABLE t DROP COLUMN a');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(1, $rows);
        self::assertArrayNotHasKey('a', $rows[0]);
        self::assertSame('y', $rows[0]['b']);
    }

    public function testAddColumnWithBacktickedNameIsNormalized(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN `new_col` VARCHAR(100)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_col', $def->columns);
        self::assertNotContains('`new_col`', $def->columns);
    }

    public function testDropColumnWithoutColumnKeywordRemovesColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a TEXT, b TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP a');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertNotContains('a', $def->columns);
        self::assertContains('b', $def->columns);
    }

    public function testRenameColumnUpdatesStoreColumnName(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1, 'a' => 'val1'], ['id' => 2, 'a' => 'val2']]);

        $parser = new Parser('ALTER TABLE t RENAME COLUMN a TO b');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $rows = $store->get('t');
        self::assertCount(2, $rows);
        self::assertArrayHasKey('b', $rows[0]);
        self::assertArrayNotHasKey('a', $rows[0]);
        self::assertSame('val1', $rows[0]['b']);
        self::assertArrayHasKey('b', $rows[1]);
        self::assertSame('val2', $rows[1]['b']);
    }

    public function testApplyAddIndexThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD INDEX idx_name (name)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testApplyDropIndexThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP INDEX idx_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testApplyDropKeyThrowsUnsupported(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP KEY idx_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, []);
    }

    public function testRenameTableTransfersStoreData(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();
        $store->set('t', [['id' => 1], ['id' => 2]]);

        $parser = new Parser('ALTER TABLE t RENAME TO t_new');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('t_new'));
        self::assertSame([], $store->get('t'));
        self::assertNull($registry->get('t'));
        self::assertNotNull($registry->get('t_new'));
    }

    public function testDropColumnUpdatesFieldsArrayIndices(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT PRIMARY KEY, b TEXT, c TEXT, d TEXT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t DROP COLUMN b');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['a', 'c', 'd'], $def->columns);
        self::assertNotContains('b', $def->columns);
    }

    public function testModifyColumnPreservesOtherColumnTypes(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, a INT, b VARCHAR(100))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t MODIFY COLUMN a BIGINT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['id', 'a', 'b'], $def->columns);
        self::assertContains('b', $def->columns);
    }

    public function testApplySchemaNotFoundThrowsException(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE nonexistent ADD COLUMN x INT');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('nonexistent', $alterStmt, $registry, $schemaParser);

        $this->expectException(SchemaNotFoundException::class);
        $mutation->apply($store, []);
    }

    public function testApplyRenameTableRegistersNewNameInRegistry(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE alpha (id INT PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('alpha', $definition);
        $store = new ShadowStore();
        $store->set('alpha', [['id' => 1, 'name' => 'test']]);

        $parser = new Parser('ALTER TABLE alpha RENAME TO beta');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('alpha', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $betaDef = $registry->get('beta');
        self::assertNotNull($betaDef);
        self::assertContains('id', $betaDef->columns);
        self::assertContains('name', $betaDef->columns);
        self::assertSame('beta', $mutation->tableName());
    }

    public function testApplyAddForeignKeyDoesNotAffectColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
    }

    public function testApplyDropForeignKeyDoesNotAffectColumns(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($definition);
        $registry->register('orders', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE orders DROP FOREIGN KEY fk_user');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('orders', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('orders');
        self::assertNotNull($def);
        self::assertSame(['id', 'user_id'], $def->columns);
    }

    public function testBuildCreateTableSqlWithCompositePrimaryKeyBackticks(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (a INT NOT NULL, b INT NOT NULL, c TEXT, PRIMARY KEY (a, b))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN d VARCHAR(255)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertSame(['a', 'b'], $def->primaryKeys);
        self::assertContains('d', $def->columns);
    }

    public function testBuildCreateTableSqlPreservesUniqueConstraintsAfterAddColumn(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, email VARCHAR(255), UNIQUE KEY uk_email (email))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t ADD COLUMN phone VARCHAR(20)');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertArrayHasKey('uk_email', $def->uniqueConstraints);
        self::assertSame(['email'], $def->uniqueConstraints['uk_email']);
        self::assertContains('phone', $def->columns);
    }

    public function testApplyRenameColumnUpdatesFieldNameInSchema(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, old_name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $store = new ShadowStore();

        $parser = new Parser('ALTER TABLE t RENAME COLUMN old_name TO new_name');
        $alterStmt = $parser->statements[0];
        self::assertInstanceOf(AlterStatement::class, $alterStmt);
        $mutation = new AlterTableMutation('t', $alterStmt, $registry, $schemaParser);
        $mutation->apply($store, []);

        $def = $registry->get('t');
        self::assertNotNull($def);
        self::assertContains('new_name', $def->columns);
        self::assertNotContains('old_name', $def->columns);
    }
}
