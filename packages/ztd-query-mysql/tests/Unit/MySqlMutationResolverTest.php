<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(MySqlMutationResolver::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(AlterTableMutation::class)]
final class MySqlMutationResolverTest extends TestCase
{
    public function testResolveInsertReturnsInsertMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $shadowStore->set('users', [['id' => '1', 'name' => 'Alice']]);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (2, 'Bob')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
    }

    public function testResolveTruncateReturnsTruncateMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'TRUNCATE TABLE users';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(TruncateMutation::class, $mutation);
    }

    public function testResolveReturnsNullForUnhandledStatement(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'SELECT 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::READ);

        self::assertNull($mutation);
    }

    public function testResolveInsertIgnoreReturnsInsertMutationWithIgnore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertOnDuplicateKeyReturnsUpsertMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Alice'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpdateReturnsUpdateMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $shadowStore->ensure('users');

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDeleteReturnsDeleteMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $shadowStore->ensure('users');

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveReplaceReturnsReplaceMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(ReplaceMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpdateMultiTableReturnsMultiUpdateMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition1 = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition1);
        $definition2 = new TableDefinition(['id', 'user_id', 'status'], ['id' => 'INT', 'user_id' => 'INT', 'status' => 'VARCHAR(50)'], ['id'], ['id'], []);
        $registry->register('orders', $definition2);

        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $shadowStore->set('orders', [['id' => 1, 'user_id' => 1, 'status' => 'pending']]);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users u, orders o SET u.name = 'Updated', o.status = 'done' WHERE u.id = o.user_id";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\MultiUpdateMutation::class, $mutation);
    }

    public function testResolveDeleteMultiTableReturnsMultiDeleteMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition1 = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition1);
        $definition2 = new TableDefinition(['id', 'user_id'], ['id' => 'INT', 'user_id' => 'INT'], ['id'], ['id'], []);
        $registry->register('orders', $definition2);

        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $shadowStore->set('orders', [['id' => 1, 'user_id' => 1]]);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE u.id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\MultiDeleteMutation::class, $mutation);
    }

    public function testResolveInsertWithOnDuplicateKeyReturnsUpsertMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = VALUES(name)";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveSelectReturnsNull(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'SELECT * FROM users';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::READ);

        self::assertNull($mutation);
    }

    public function testResolveUpdateWithShadowDataButNoDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDeleteWithShadowDataButNoDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "DELETE FROM users WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertWithQualifiedOnDuplicateKey(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE users.name = VALUES(name)";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
    }

    public function testResolveCreateTableReturnsCreateTableMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\CreateTableMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveDropTableReturnsDropTableMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE users';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\DropTableMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveAlterTableReturnsAlterTableMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('t', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'ALTER TABLE t ADD COLUMN name VARCHAR(255)';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Platform\MySql\Mutation\AlterTableMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveCreateTableLikeReturnsCreateTableLikeMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('source', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest LIKE source';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\CreateTableLikeMutation::class, $mutation);
        self::assertSame('dest', $mutation->tableName());
    }

    public function testResolveCreateTableAsSelectReturnsCreateTableAsSelectMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest AS SELECT id, name FROM source';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation::class, $mutation);
        self::assertSame('dest', $mutation->tableName());
    }

    public function testUpdateEnsuresTableInShadowStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertSame([], $shadowStore->get('users'));
    }

    public function testUpdateUsesColumnsFromStoreWhenAvailable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => '1', 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
    }

    public function testMultiTableUpdateEnsuresTablesInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $userDef = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $orderDef = new TableDefinition(['id', 'user_id', 'status'], ['id' => 'INT', 'user_id' => 'INT', 'status' => 'VARCHAR(50)'], ['id'], ['id'], []);
        $registry->register('users', $userDef);
        $registry->register('orders', $orderDef);

        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $shadowStore->set('orders', [['id' => 1, 'user_id' => 1, 'status' => 'pending']]);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users u, orders o SET u.name = 'X', o.status = 'done' WHERE u.id = o.user_id";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(MultiUpdateMutation::class, $mutation);
    }

    public function testDeleteEnsuresTableInShadowStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertSame([], $shadowStore->get('users'));
    }

    public function testDeleteUsesColumnsFromShadowStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => '1', 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
    }

    public function testMultiDeleteEnsuresTablesInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $userDef = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $orderDef = new TableDefinition(['id', 'user_id', 'status'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $userDef);
        $registry->register('orders', $orderDef);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'old'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(MultiDeleteMutation::class, $mutation);
        self::assertSame([], $shadowStore->get('users'));
        self::assertSame([], $shadowStore->get('orders'));
    }

    public function testInsertOnDuplicateKeyUpdateReturnsUpsert(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Bob'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testInsertOnDuplicateKeyUpdateWithQualifiedColumn(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE `users`.`name` = 'Bob'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
    }

    public function testInsertIgnoreReturnsInsertMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(255)'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
    }

    public function testCreateTableIfNotExistsWhenExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY)';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableMutation::class, $mutation);
    }

    public function testCreateTableWithoutIfNotExistsThrowsWhenExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE users (id INT PRIMARY KEY)';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);
    }

    public function testDropTableIfExistsWhenNotExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE IF EXISTS users';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(DropTableMutation::class, $mutation);
    }

    public function testDropTableWithoutIfExistsThrowsWhenNotExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE users';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);
    }

    public function testCreateTableLikeThrowsForUnknownSource(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE t LIKE unknown_table';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);
    }

    public function testResolveUpdateUsesColumnsFromShadowStoreFirst(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'a@b.c']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpdateUsesColumnsFromRegistryWhenStoreEmpty(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
        self::assertSame([], $shadowStore->get('users'));
    }

    public function testResolveUpdateThrowsWhenNoSchemaAvailable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);
    }

    public function testResolveDeleteUsesColumnsFromShadowStoreFirst(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDeleteUsesColumnsFromRegistryWhenStoreEmpty(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertOnDuplicateKeyExtractsUpdateColumns(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.c') ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email)";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertIgnoreIncludesPrimaryKeys(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveCreateTableAsSelectExtractsColumnNames(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE archive AS SELECT id, name AS full_name FROM users';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
        self::assertSame('archive', $mutation->tableName());
    }

    public function testResolveCreateTableAsSelectExtractsAliasedAndExprColumns(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE stats AS SELECT COUNT(*) AS cnt, department FROM employees GROUP BY department';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
        self::assertSame('stats', $mutation->tableName());
    }

    public function testResolveReplaceReturnsPrimaryKeysFromRegistry(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(ReplaceMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDropTableIfExistsDoesNotThrow(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE IF EXISTS nonexistent';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(DropTableMutation::class, $mutation);
        self::assertSame('nonexistent', $mutation->tableName());
    }

    public function testResolveAlterTableThrowsForUnknownTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'ALTER TABLE unknown_table ADD COLUMN x INT';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);
    }

    public function testResolveMultiUpdateThrowsForUnknownTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users, orders SET users.name = 'x' WHERE users.id = orders.user_id";
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);
    }

    public function testResolveCreateTableIfNotExistsSkipsWhenExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE IF NOT EXISTS t (id INT PRIMARY KEY)';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveDeleteThrowsWhenNoSchemaContextExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);
    }

    public function testResolveInsertOnDuplicateKeyWithDottedColumn(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO t (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE t.name = VALUES(name)";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveMultiDeleteEnsuresAllTablesInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def1 = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($def1);
        $registry->register('users', $def1);
        $def2 = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($def2);
        $registry->register('orders', $def2);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE users, orders FROM users INNER JOIN orders ON users.id = orders.user_id WHERE users.id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(MultiDeleteMutation::class, $mutation);
        self::assertSame([], $shadowStore->get('users'));
        self::assertSame([], $shadowStore->get('orders'));
    }

    public function testResolveMultiDeleteThrowsForUnknownTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def1 = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($def1);
        $registry->register('users', $def1);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE users, unknown_tbl FROM users INNER JOIN unknown_tbl ON users.id = unknown_tbl.user_id';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);
    }

    public function testResolveInsertIgnoreReturnsPrimaryKeysFromDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertIgnoreWithoutDefinitionReturnEmptyPks(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'A']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpsertReturnsPrimaryKeysFromDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Bob'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveMultiUpdateReturnsPrimaryKeysFromDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def1 = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def1);
        $registry->register('users', $def1);
        $def2 = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($def2);
        $registry->register('orders', $def2);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users, orders SET users.name = 'Bob' WHERE users.id = orders.user_id";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(MultiUpdateMutation::class, $mutation);
    }

    public function testResolveMultiDeleteReturnsPrimaryKeysFromDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def1 = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($def1);
        $registry->register('users', $def1);
        $def2 = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT)');
        self::assertNotNull($def2);
        $registry->register('orders', $def2);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE users, orders FROM users INNER JOIN orders ON users.id = orders.user_id WHERE users.id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(MultiDeleteMutation::class, $mutation);
    }

    public function testResolveDeleteEnsuresTargetTableInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveInsertOnDuplicateKeyWithBacktickedColumn(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO t (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE `name` = 'Bob'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveCreateTableAsSelectWithColumnNames(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE src (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('src', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest AS SELECT id, name FROM src';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
    }

    public function testResolveCreateTableAsSelectExtractsColumnsFromAliasedExprs(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest AS SELECT id AS uid, name AS label FROM src';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
        $mutation->apply($shadowStore, [['uid' => 1, 'label' => 'Alice']]);
        $def = $registry->get('dest');
        self::assertNotNull($def);
        self::assertContains('uid', $def->columns);
        self::assertContains('label', $def->columns);
    }

    public function testResolveCreateTableAsSelectExtractsPlainColumnNames(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest AS SELECT id, name FROM src';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Bob']]);
        $def = $registry->get('dest');
        self::assertNotNull($def);
        self::assertContains('id', $def->columns);
        self::assertContains('name', $def->columns);
    }

    public function testResolveCreateTableAsSelectHandlesExpressionWithoutAlias(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest AS SELECT COUNT(*) AS cnt FROM src';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);
        $mutation->apply($shadowStore, [['cnt' => 5]]);
        $def = $registry->get('dest');
        self::assertNotNull($def);
        self::assertContains('cnt', $def->columns);
    }

    public function testResolveInsertIgnoreProducesInsertMutation(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT IGNORE INTO t (id, name) VALUES (1, 'Alice')";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(InsertMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveUpdateMultiTableReturnsPrimaryKeysFromDefinition(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDeleteWithSingleTargetReturnsPrimaryKeys(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE orders (id INT PRIMARY KEY, total DECIMAL(10,2))');
        self::assertNotNull($def);
        $registry->register('orders', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DELETE FROM orders WHERE id = 1';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(DeleteMutation::class, $mutation);
        self::assertSame('orders', $mutation->tableName());
    }

    public function testResolveInsertOnDuplicateKeyExtractsQualifiedColumnName(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = "INSERT INTO t (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE t.name = 'Bob'";
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::WRITE_SIMULATED);

        self::assertInstanceOf(UpsertMutation::class, $mutation);
        self::assertSame('t', $mutation->tableName());
    }

    public function testResolveDropTableWithIfExistsDoesNotThrowForMissingTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE IF EXISTS nonexistent';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(DropTableMutation::class, $mutation);
    }

    public function testResolveDropTableWithoutIfExistsThrowsForMissingTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'DROP TABLE nonexistent';
        $statements = $parser->parse($sql);

        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);
    }

    public function testResolveCreateTableIfNotExistsDoesNotThrowForExistingTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE IF NOT EXISTS t (id INT)';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableMutation::class, $mutation);
    }

    public function testResolveCreateTableLike(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE src (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('src', $def);

        $selectTransformer = new SelectTransformer();
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $resolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        $sql = 'CREATE TABLE dest LIKE src';
        $statements = $parser->parse($sql);
        $mutation = $resolver->resolve($sql, $statements[0], QueryKind::DDL_SIMULATED);

        self::assertInstanceOf(CreateTableLikeMutation::class, $mutation);
    }
}
