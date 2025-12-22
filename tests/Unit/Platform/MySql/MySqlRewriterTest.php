<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\QueryGuard;
use ZtdQuery\Rewrite\Projection\WriteProjection;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\AlterTableMutation;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;

final class MySqlRewriterTest extends TestCase
{
    public function testRewriteReadAddsCte(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite('SELECT * FROM users');

        $this->assertSame(QueryKind::READ, $plan->kind());
        $this->assertStringStartsWith('WITH `users` AS', $plan->sql());
    }

    public function testRewriteUpdateCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(UpdateMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testRewriteInsertCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(InsertMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testRewriteDeleteCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(DeleteMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testRewriteForbiddenStatement(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite('CREATE DATABASE test');

        $this->assertSame(QueryKind::FORBIDDEN, $plan->kind());
        $this->assertSame('CREATE DATABASE test', $plan->sql());
    }

    public function testRewriteTruncateCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite('TRUNCATE TABLE users');

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(TruncateMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testTruncateMutationClearsTable(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection);
        $plan = $rewriter->rewrite('TRUNCATE TABLE users');

        // Apply the mutation
        $mutation = $plan->mutation();
        $this->assertInstanceOf(TruncateMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the table is now empty
        $this->assertSame([], $shadowStore->get('users'));
    }

    public function testRewriteReplaceCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Bob')");

        $this->assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $this->assertInstanceOf(ReplaceMutation::class, $plan->mutation());
        $this->assertStringContainsString('SELECT', $plan->sql());
    }

    public function testReplaceMutationReplacesExistingRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Bob')");

        $mutation = $plan->mutation();
        $this->assertInstanceOf(ReplaceMutation::class, $mutation);
        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Bob']]);

        $rows = $shadowStore->get('users');
        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    public function testReplaceMutationInsertsNewRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (2, 'Bob')");

        $mutation = $plan->mutation();
        $this->assertInstanceOf(ReplaceMutation::class, $mutation);
        $mutation->apply($shadowStore, [['id' => 2, 'name' => 'Bob']]);

        $rows = $shadowStore->get('users');
        $this->assertCount(2, $rows);
    }

    public function testRewriteCreateTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(CreateTableMutation::class, $plan->mutation());
    }

    public function testCreateTableMutationRegistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(CreateTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was registered
        $columns = $schema->getColumns('users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);

        // Verify the table exists in shadow store (empty)
        $this->assertSame([], $shadowStore->get('users'));
    }

    public function testCreateTableIfNotExistsSkipsExistingTable(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(CreateTableMutation::class, $mutation);

        // Should not throw, just skip
        $mutation->apply($shadowStore, []);

        // Original schema should be preserved
        $columns = $schema->getColumns('users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertNotContains('email', $columns);
    }

    public function testRewriteDropTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('DROP TABLE users');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(DropTableMutation::class, $plan->mutation());
    }

    public function testDropTableMutationUnregistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('DROP TABLE users');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(DropTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was unregistered
        $this->assertNull($schema->getColumns('users'));

        // Verify the table data was cleared
        $this->assertSame([], $shadowStore->get('users'));
    }

    public function testDropTableIfExistsSkipsNonExistentTable(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('DROP TABLE IF EXISTS users');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(DropTableMutation::class, $mutation);

        // Should not throw, just skip
        $mutation->apply($shadowStore, []);
    }

    public function testRewriteAlterTableAddColumnCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users ADD COLUMN email VARCHAR(255)');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(AlterTableMutation::class, $plan->mutation());
    }

    public function testAlterTableAddColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users ADD COLUMN email VARCHAR(255)');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was updated
        $columns = $schema->getColumns('users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function testAlterTableDropColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users DROP COLUMN email');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was updated
        $columns = $schema->getColumns('users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertNotContains('email', $columns);

        // Verify the data was updated
        $rows = $shadowStore->get('users');
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('email', $rows[0]);
    }

    public function testAlterTableModifyColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users MODIFY COLUMN name VARCHAR(500)');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was updated
        $columnTypes = $schema->getColumnTypes('users');
        $this->assertNotNull($columnTypes);
        $this->assertSame('VARCHAR(500)', $columnTypes['name']);
    }

    public function testAlterTableChangeColumnRenamesColumn(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was updated
        $columns = $schema->getColumns('users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('full_name', $columns);
        $this->assertNotContains('name', $columns);

        // Verify the data was updated
        $rows = $shadowStore->get('users');
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('full_name', $rows[0]);
        $this->assertSame('Alice', $rows[0]['full_name']);
        $this->assertArrayNotHasKey('name', $rows[0]);
    }

    public function testAlterTableRenameRenamesTable(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('ALTER TABLE users RENAME TO members');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the schema was renamed
        $this->assertNull($schema->getColumns('users'));
        $columns = $schema->getColumns('members');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);

        // Verify the data was moved
        $this->assertSame([], $shadowStore->get('users'));
        $rows = $shadowStore->get('members');
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testRewriteMultipleProcessesEachStatement(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $multiPlan = $rewriter->rewriteMultiple('SELECT * FROM users; INSERT INTO users (id, name) VALUES (2, \'Bob\')');

        $this->assertSame(2, $multiPlan->count());
        $this->assertTrue($multiPlan->allAllowed());

        $firstPlan = $multiPlan->get(0);
        $this->assertNotNull($firstPlan);
        $this->assertSame(QueryKind::READ, $firstPlan->kind());

        $secondPlan = $multiPlan->get(1);
        $this->assertNotNull($secondPlan);
        $this->assertSame(QueryKind::WRITE_SIMULATED, $secondPlan->kind());
        $this->assertInstanceOf(InsertMutation::class, $secondPlan->mutation());
    }

    public function testRewriteMultipleWithForbiddenStatement(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $multiPlan = $rewriter->rewriteMultiple('SELECT 1; DROP DATABASE test');

        $this->assertSame(2, $multiPlan->count());
        $this->assertFalse($multiPlan->allAllowed());

        $firstPlan = $multiPlan->get(0);
        $this->assertNotNull($firstPlan);
        $this->assertSame(QueryKind::READ, $firstPlan->kind());

        $secondPlan = $multiPlan->get(1);
        $this->assertNotNull($secondPlan);
        $this->assertSame(QueryKind::FORBIDDEN, $secondPlan->kind());
    }

    public function testRewriteSingleStatementStillWorks(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);

        // rewrite() should work for single statements
        $plan = $rewriter->rewrite('SELECT * FROM users');
        $this->assertSame(QueryKind::READ, $plan->kind());

        // rewrite() should return FORBIDDEN for multiple statements (use rewriteMultiple instead)
        $plan = $rewriter->rewrite('SELECT 1; SELECT 2');
        $this->assertSame(QueryKind::FORBIDDEN, $plan->kind());
    }

    public function testRewriteCreateTableLikeCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE members LIKE users');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(CreateTableLikeMutation::class, $plan->mutation());
    }

    public function testCreateTableLikeCopiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE members LIKE users');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(CreateTableLikeMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the new table was created with same columns
        $columns = $schema->getColumns('members');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);

        // Verify the new table is empty
        $this->assertSame([], $shadowStore->get('members'));
    }

    public function testRewriteCreateTableAsSelectCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE active_users AS SELECT id, name FROM users WHERE id > 0');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(CreateTableAsSelectMutation::class, $plan->mutation());
    }

    public function testCreateTableAsSelectCreatesTableWithData(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TABLE active_users AS SELECT id, name FROM users');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);

        // Apply with mock result rows
        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]);

        // Verify the new table was created
        $columns = $schema->getColumns('active_users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);

        // Verify the data was populated
        $rows = $shadowStore->get('active_users');
        $this->assertCount(2, $rows);
    }

    public function testRewriteCreateTemporaryTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TEMPORARY TABLE temp_users (id INT, name VARCHAR(255))');

        $this->assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        $this->assertInstanceOf(CreateTableMutation::class, $plan->mutation());
    }

    public function testCreateTemporaryTableRegistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $schema = new SchemaRegistry();
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);
        $plan = $rewriter->rewrite('CREATE TEMPORARY TABLE temp_users (id INT, name VARCHAR(255))');

        $mutation = $plan->mutation();
        $this->assertInstanceOf(CreateTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        // Verify the table was registered
        $columns = $schema->getColumns('temp_users');
        $this->assertNotNull($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
    }

    /**
     * Test that REPLACE with empty values returns FORBIDDEN instead of throwing exception.
     *
     * Bug: REPLACE DELAYED INTO table VALUE( ) caused RuntimeException
     * Expected: Should return QueryKind::FORBIDDEN for invalid SQL
     */
    public function testReplaceWithEmptyValuesReturnsForbidden(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);

        // This should not throw RuntimeException, but return FORBIDDEN
        $plan = $rewriter->rewrite('REPLACE INTO users VALUE( )');

        $this->assertSame(QueryKind::FORBIDDEN, $plan->kind());
    }

    /**
     * Test that REPLACE with mismatched column count returns FORBIDDEN.
     */
    public function testReplaceWithMismatchedColumnCountReturnsForbidden(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);
        $projection = new WriteProjection($shadowStore, $schema, $shadowing, new UpdateTransformer(), new DeleteTransformer());

        $rewriter = new MySqlRewriter(new QueryGuard(), $shadowStore, $shadowing, $projection, null, $schema);

        // 2 columns (id, name) but only 1 value - should return FORBIDDEN
        $plan = $rewriter->rewrite('REPLACE INTO users (id, name) VALUES (1)');

        $this->assertSame(QueryKind::FORBIDDEN, $plan->kind());
    }
}
