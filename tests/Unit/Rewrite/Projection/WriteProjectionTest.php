<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Projection;

use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\Projection\WriteProjection;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\TestCase;

final class WriteProjectionTest extends TestCase
{
    public function testProjectsUpdateToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(UpdateMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
        $this->assertStringContainsString('FROM `users`', $plan->sql());
        $this->assertStringContainsString('WHERE id = 1', $plan->sql());
    }

    public function testProjectsDeleteToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(DeleteMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT `users`.`id` AS `id`', $plan->sql());
        $this->assertStringContainsString('FROM users', $plan->sql());
    }

    public function testProjectsInsertToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice')";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(InsertMutation::class, $plan->mutation());
        $this->assertStringContainsString("SELECT 1 AS `id`, 'Alice' AS `name`", $plan->sql());
    }

    public function testProjectsInsertWithSetSyntax(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users SET id = 1, name = 'Alice'";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertStringContainsString('SELECT', $plan->sql());
        $this->assertStringContainsString("id`", $plan->sql());
        $this->assertStringContainsString("name`", $plan->sql());
    }

    public function testProjectsInsertSelectToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $schema->register('old_users', 'CREATE TABLE old_users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = 'INSERT INTO users (id, name) SELECT id, name FROM old_users WHERE active = 1';
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(InsertMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
        $this->assertStringContainsString('`id`', $plan->sql());
        $this->assertStringContainsString('`name`', $plan->sql());
    }

    public function testProjectsInsertSelectWithoutColumnList(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users SELECT 1 AS id, 'Alice' AS name";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(InsertMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testInsertSelectColumnCountMismatchThrows(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $schema->register('old_users', 'CREATE TABLE old_users (id INT)');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = 'INSERT INTO users (id, name) SELECT id FROM old_users';
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('column count');

        $projection->project($sql, $statement);
    }

    public function testProjectsInsertIgnore(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Existing']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(InsertMutation::class, $plan->mutation());

        // Apply the mutation and verify duplicate is ignored
        $plan->mutation()->apply($shadowStore, [['id' => 1, 'name' => 'Alice']]);

        // Should still have original row, not the new one
        $rows = $shadowStore->get('users');
        $this->assertCount(1, $rows);
        $this->assertSame('Existing', $rows[0]['name']);
    }

    public function testInsertIgnoreAddsNonDuplicateRows(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT IGNORE INTO users (id, name) VALUES (2, 'Bob')";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);
        $mutation = $plan->mutation();
        $this->assertNotNull($mutation);
        $mutation->apply($shadowStore, [['id' => 2, 'name' => 'Bob']]);

        // Should have both rows
        $rows = $shadowStore->get('users');
        $this->assertCount(2, $rows);
    }

    public function testProjectsInsertOnDuplicateKeyUpdate(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Existing', 'email' => 'old@example.com']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com') ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email)";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(UpsertMutation::class, $plan->mutation());
    }

    public function testInsertOnDuplicateKeyUpdateUpdatesExistingRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Existing', 'email' => 'old@example.com']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com') ON DUPLICATE KEY UPDATE name = VALUES(name)";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);
        $mutation = $plan->mutation();
        $this->assertNotNull($mutation);
        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        // Should have updated name but kept original email
        $rows = $shadowStore->get('users');
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testInsertOnDuplicateKeyUpdateInsertsNewRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users (id, name) VALUES (2, 'Bob') ON DUPLICATE KEY UPDATE name = VALUES(name)";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $plan = $projection->project($sql, $statement);
        $mutation = $plan->mutation();
        $this->assertNotNull($mutation);
        $mutation->apply($shadowStore, [['id' => 2, 'name' => 'Bob']]);

        // Should have both rows
        $rows = $shadowStore->get('users');
        $this->assertCount(2, $rows);
    }

    public function testInsertValueCountMismatchThrows(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "INSERT INTO users (id, name) VALUES (1)";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
            $this->fail('Expected InsertStatement.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('values count does not match');

        $projection->project($sql, $statement);
    }

    public function testUpdateWithoutSchemaReturnsUnknownSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $plan = $projection->project($sql, $statement);

        // Without schema, UPDATE returns UNKNOWN_SCHEMA with table name
        $this->assertSame(QueryKind::UNKNOWN_SCHEMA, $plan->kind());
        $this->assertSame('users', $plan->unknownIdentifier());
    }

    public function testProjectsMultiTableDeleteToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $schema->register('orders', 'CREATE TABLE orders (id INT PRIMARY KEY, user_id INT, status VARCHAR(50))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(MultiDeleteMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testMultiTableDeleteMutationDeletesFromBothTables(): void
    {
        $shadowStore = new ShadowStore();
        // Use user_id as PK for users and order_id as PK for orders to avoid column conflicts
        $shadowStore->set('users', [
            ['user_id' => 1, 'name' => 'Alice'],
            ['user_id' => 2, 'name' => 'Bob'],
        ]);
        $shadowStore->set('orders', [
            ['order_id' => 101, 'user_id' => 1, 'status' => 'canceled'],
            ['order_id' => 102, 'user_id' => 2, 'status' => 'completed'],
        ]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (user_id INT PRIMARY KEY, name VARCHAR(255))');
        $schema->register('orders', 'CREATE TABLE orders (order_id INT PRIMARY KEY, user_id INT, status VARCHAR(50))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.user_id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $plan = $projection->project($sql, $statement);
        $mutation = $plan->mutation();
        $this->assertNotNull($mutation);

        // Apply the mutation with rows matching the deleted data
        // The rows contain PKs from both tables (user_id from users, order_id from orders)
        $deletedRows = [
            ['user_id' => 1, 'name' => 'Alice', 'order_id' => 101, 'status' => 'canceled'],
        ];
        $mutation->apply($shadowStore, $deletedRows);

        // Both tables should have rows deleted
        $this->assertCount(1, $shadowStore->get('users'));
        $this->assertCount(1, $shadowStore->get('orders'));
        $this->assertSame('Bob', $shadowStore->get('users')[0]['name']);
        $this->assertSame('completed', $shadowStore->get('orders')[0]['status']);
    }

    public function testSingleTableDeleteUsesDeleteMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(DeleteMutation::class, $plan->mutation());
    }

    public function testProjectsMultiTableUpdateToSelect(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (user_id INT PRIMARY KEY, name VARCHAR(255))');
        $schema->register('orders', 'CREATE TABLE orders (order_id INT PRIMARY KEY, user_id INT, status VARCHAR(50))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'Updated', o.status = 'processed' WHERE u.user_id = o.user_id";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(MultiUpdateMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testSingleTableUpdateUsesUpdateMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);

        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $plan = $projection->project($sql, $statement);

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(UpdateMutation::class, $plan->mutation());
    }
}
