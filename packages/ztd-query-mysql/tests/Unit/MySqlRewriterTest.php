<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\RewriterContractTest;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
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

#[CoversClass(MySqlRewriter::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
final class MySqlRewriterTest extends RewriterContractTest
{
    protected function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry): SqlRewriter
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);

        return new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);
    }

    protected function createSchemaParser(): SchemaParser
    {
        return new MySqlSchemaParser(new MySqlParser());
    }

    protected function selectSql(): string
    {
        return 'SELECT id, name, email FROM users WHERE id = 1';
    }

    protected function insertSql(): string
    {
        return "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')";
    }

    protected function updateSql(): string
    {
        return "UPDATE users SET name = 'Bob' WHERE id = 1";
    }

    protected function deleteSql(): string
    {
        return 'DELETE FROM users WHERE id = 1';
    }

    protected function createTableSql(): string
    {
        return 'CREATE TABLE orders (id INT PRIMARY KEY, amount DECIMAL(10,2))';
    }

    protected function dropTableSql(): string
    {
        return 'DROP TABLE IF EXISTS orders';
    }

    protected function unsupportedSql(): string
    {
        return 'CREATE DATABASE test_db';
    }

    protected function usersCreateTableSql(): string
    {
        return <<<'SQL'
            CREATE TABLE users (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL;
    }

    public function testRewriteReadAddsCte(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringStartsWith('WITH `users` AS', $plan->sql());
    }

    public function testRewriteUpdateCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'UPDATE result-select must start with SELECT or WITH...SELECT');
    }

    public function testRewriteInsertCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'INSERT result-select must start with SELECT or WITH...SELECT');
    }

    public function testRewriteDeleteCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'DELETE result-select must start with SELECT or WITH...SELECT');
    }

    public function testRewriteForbiddenStatementThrowsException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Statement type not supported');
        $rewriter->rewrite('CREATE DATABASE test');
    }

    public function testRewriteTruncateCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('TRUNCATE TABLE users');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(TruncateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'TRUNCATE result-select must start with SELECT or WITH...SELECT');
    }

    public function testTruncateMutationClearsTable(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('TRUNCATE TABLE users');

        $mutation = $plan->mutation();
        self::assertInstanceOf(TruncateMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        self::assertSame([], $shadowStore->get('users'));
    }

    public function testRewriteReplaceCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Bob')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'REPLACE result-select must start with SELECT or WITH...SELECT');
    }

    public function testReplaceMutationReplacesExistingRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Bob')");

        $mutation = $plan->mutation();
        self::assertInstanceOf(ReplaceMutation::class, $mutation);
        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Bob']]);

        $rows = $shadowStore->get('users');
        self::assertCount(1, $rows);
        self::assertSame('Bob', $rows[0]['name']);
    }

    public function testReplaceMutationInsertsNewRow(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (2, 'Bob')");

        $mutation = $plan->mutation();
        self::assertInstanceOf(ReplaceMutation::class, $mutation);
        $mutation->apply($shadowStore, [['id' => 2, 'name' => 'Bob']]);

        $rows = $shadowStore->get('users');
        self::assertCount(2, $rows);
    }

    public function testRewriteCreateTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(CreateTableMutation::class, $plan->mutation());
    }

    public function testCreateTableMutationRegistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $mutation = $plan->mutation();
        self::assertInstanceOf(CreateTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);

        self::assertSame([], $shadowStore->get('users'));
    }

    public function testCreateTableIfNotExistsSkipsExistingTable(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY)');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');

        $mutation = $plan->mutation();
        self::assertInstanceOf(CreateTableMutation::class, $mutation);

        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertNotContains('email', $definition->columns);
    }

    public function testRewriteDropTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DROP TABLE users');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(DropTableMutation::class, $plan->mutation());
    }

    public function testDropTableMutationUnregistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DROP TABLE users');

        $mutation = $plan->mutation();
        self::assertInstanceOf(DropTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        self::assertNull($registry->get('users'));

        self::assertSame([], $shadowStore->get('users'));
    }

    public function testDropTableIfExistsSkipsNonExistentTable(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DROP TABLE IF EXISTS users');

        $mutation = $plan->mutation();
        self::assertInstanceOf(DropTableMutation::class, $mutation);

        $mutation->apply($shadowStore, []);
    }

    public function testDropNonExistentTableThrowsUnknownSchemaException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        self::expectExceptionMessage('Unknown table');

        $rewriter->rewrite('DROP TABLE users');
    }

    public function testRewriteAlterTableAddColumnCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users ADD COLUMN email VARCHAR(255)');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(AlterTableMutation::class, $plan->mutation());
    }

    public function testAlterTableAddColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users ADD COLUMN email VARCHAR(255)');

        $mutation = $plan->mutation();
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
        self::assertContains('email', $definition->columns);
    }

    public function testAlterTableDropColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users DROP COLUMN email');

        $mutation = $plan->mutation();
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
        self::assertNotContains('email', $definition->columns);

        $rows = $shadowStore->get('users');
        self::assertCount(1, $rows);
        self::assertArrayNotHasKey('email', $rows[0]);
    }

    public function testAlterTableModifyColumnModifiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users MODIFY COLUMN name VARCHAR(500)');

        $mutation = $plan->mutation();
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertSame('VARCHAR(500)', $definition->columnTypes['name']);
    }

    public function testAlterTableChangeColumnRenamesColumn(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)');

        $mutation = $plan->mutation();
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('full_name', $definition->columns);
        self::assertNotContains('name', $definition->columns);

        $rows = $shadowStore->get('users');
        self::assertCount(1, $rows);
        self::assertArrayHasKey('full_name', $rows[0]);
        self::assertSame('Alice', $rows[0]['full_name']);
        self::assertArrayNotHasKey('name', $rows[0]);
    }

    public function testAlterTableRenameRenamesTable(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('ALTER TABLE users RENAME TO members');

        $mutation = $plan->mutation();
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        self::assertNull($registry->get('users'));
        $definition = $registry->get('members');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);

        self::assertSame([], $shadowStore->get('users'));
        $rows = $shadowStore->get('members');
        self::assertCount(1, $rows);
        self::assertSame('Alice', $rows[0]['name']);
    }

    public function testAlterNonExistentTableThrowsUnknownSchemaException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        self::expectExceptionMessage('Unknown table');

        $rewriter->rewrite('ALTER TABLE users ADD COLUMN email VARCHAR(255)');
    }

    public function testRewriteMultipleProcessesEachStatement(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $multiPlan = $rewriter->rewriteMultiple('SELECT * FROM users; INSERT INTO users (id, name) VALUES (2, \'Bob\')');

        self::assertSame(2, $multiPlan->count());
        $firstPlan = $multiPlan->get(0);
        self::assertNotNull($firstPlan);
        self::assertSame(QueryKind::READ, $firstPlan->kind());

        $secondPlan = $multiPlan->get(1);
        self::assertNotNull($secondPlan);
        self::assertSame(QueryKind::WRITE_SIMULATED, $secondPlan->kind());
        self::assertInstanceOf(InsertMutation::class, $secondPlan->mutation());
    }

    public function testRewriteMultipleWithForbiddenStatementThrowsException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);

        $rewriter->rewriteMultiple('SELECT 1; DROP DATABASE test');
    }

    public function testRewriteSingleStatementStillWorks(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testRewriteMultipleStatementsThrowsException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Multi-statement');
        $rewriter->rewrite('SELECT 1; SELECT 2');
    }

    public function testRewriteCreateTableLikeCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE members LIKE users');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(CreateTableLikeMutation::class, $plan->mutation());
    }

    public function testCreateTableLikeCopiesSchema(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE members LIKE users');

        $mutation = $plan->mutation();
        self::assertInstanceOf(CreateTableLikeMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('members');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
        self::assertContains('email', $definition->columns);

        self::assertSame([], $shadowStore->get('members'));
    }

    public function testCreateTableLikeWithUnknownSourceThrowsUnknownSchemaException(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        self::expectExceptionMessage('Unknown table');

        $rewriter->rewrite('CREATE TABLE members LIKE users');
    }

    public function testRewriteCreateTableAsSelectCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE active_users AS SELECT id, name FROM users WHERE id > 0');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(CreateTableAsSelectMutation::class, $plan->mutation());
    }

    public function testCreateTableAsSelectCreatesTableWithData(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE active_users AS SELECT id, name FROM users');

        $mutation = $plan->mutation();
        self::assertInstanceOf(CreateTableAsSelectMutation::class, $mutation);

        $mutation->apply($shadowStore, [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]);

        $definition = $registry->get('active_users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);

        $rows = $shadowStore->get('active_users');
        self::assertCount(2, $rows);
    }

    public function testRewriteCreateTemporaryTableCreatesMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TEMPORARY TABLE temp_users (id INT, name VARCHAR(255))');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(CreateTableMutation::class, $plan->mutation());
    }

    public function testCreateTemporaryTableRegistersSchema(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();

        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TEMPORARY TABLE temp_users (id INT, name VARCHAR(255))');

        $mutation = $plan->mutation();
        self::assertInstanceOf(CreateTableMutation::class, $mutation);
        $mutation->apply($shadowStore, []);

        $definition = $registry->get('temp_users');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
    }

    /**
     * Test that REPLACE with empty values throws UnsupportedSqlException.
     *
     * Bug: REPLACE DELAYED INTO table VALUE( ) caused RuntimeException
     * Expected: Should throw UnsupportedSqlException for invalid SQL
     */
    public function testReplaceWithEmptyValuesThrowsException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);

        $rewriter->rewrite('REPLACE INTO users VALUE( )');
    }

    /**
     * Test that REPLACE with mismatched column count throws UnsupportedSqlException.
     */
    public function testReplaceWithMismatchedColumnCountThrowsException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);

        $rewriter->rewrite('REPLACE INTO users (id, name) VALUES (1)');
    }

    public function testRewriteSelectWithUnknownTableThrowsUnknownSchemaException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('known', [['id' => 1]]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE known (id INT)');
        self::assertNotNull($def);
        $registry->register('known', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM unknown_table');
    }

    public function testRewriteSelectWithJoinAndUnknownTableThrowsUnknownSchemaException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('known', [['id' => 1]]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE known (id INT)');
        self::assertNotNull($def);
        $registry->register('known', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM known JOIN unknown_table ON known.id = unknown_table.id');
    }

    public function testRewriteSelectWithNoSchemaContextDoesNotThrow(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM anything');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame('SELECT * FROM anything', $plan->sql());
    }

    public function testRewriteUpdateEnsuresDmlTableInShadowStore(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertSame([], $shadowStore->get('users'));
    }

    public function testRewriteDeleteEnsuresDmlTableInShadowStore(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("DELETE FROM users WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertSame([], $shadowStore->get('users'));
    }

    public function testRewriteAlterTableWithUnsupportedSetDefaultThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ALTER COLUMN name SET DEFAULT 'foo'");
    }

    public function testRewriteAlterTableWithDropDefaultThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ALTER COLUMN name DROP DEFAULT");
    }

    public function testRewriteAlterTableWithOrderByThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ORDER BY name");
    }

    public function testRewriteAlterTableAddIndexThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ADD INDEX idx_name (name)");
    }

    public function testRewriteAlterTableDropIndexThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users DROP INDEX idx_name");
    }

    public function testRewriteReadWithRegistryButNoShadowStoreDataAddsCtesForKnownTables(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('WITH', $plan->sql());
        self::assertStringContainsString('`users`', $plan->sql());
    }

    public function testRewriteInsertWithOnDuplicateKeyUpdateCreatesUpsertMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Bob'");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
    }

    public function testRewriteCreateTableAsSelectTransformsCte(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('source', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE source (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('source', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE dest AS SELECT id, name FROM source');
        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertStringContainsString('SELECT', $plan->sql());
        self::assertStringNotContainsString('SELECT 1 WHERE FALSE', $plan->sql());
    }

    public function testRewriteReplaceWithoutColumnsThrowsWhenNoContext(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Cannot determine columns');
        $rewriter->rewrite("REPLACE INTO users VALUES (1, 'Alice')");
    }

    public function testRewriteReplaceWithColumnsDefined(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testRewriteWithStatementSelectUsesClassify(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('WITH cte AS (SELECT 1) SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testRewriteAlterTableConvertToCharsetThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4");
    }

    public function testRewriteEmptySqlThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Empty or unparseable');
        $rewriter->rewrite('');
    }

    public function testRewriteMultipleEmptySqlThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewriteMultiple('');
    }

    public function testBuildTableContextMergesShadowDataAndRegistryDefinitions(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $usersDef = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($usersDef);
        $registry->register('users', $usersDef);
        $ordersDef = $schemaParser->parse('CREATE TABLE orders (id INT, user_id INT)');
        self::assertNotNull($ordersDef);
        $registry->register('orders', $ordersDef);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users JOIN orders ON users.id = orders.user_id');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('`users` AS', $plan->sql());
        self::assertStringContainsString('`orders` AS', $plan->sql());
    }

    public function testRewriteInsertIgnoreCreatesInsertMutationWithIgnore(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT IGNORE INTO users (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
    }

    public function testRewriteAlterTableAddFulltextIndexThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ADD FULLTEXT INDEX ft_name (name)");
    }

    public function testRewriteCreateTableThatAlreadyExistsThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT)');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('CREATE TABLE users (id INT, name VARCHAR(255))');
    }

    public function testRewriteReplaceWithColumnsSucceeds(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testRewriteSelectWithUnknownTableThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE known_table (id INT)');
        self::assertNotNull($def);
        $registry->register('known_table', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM unknown_table');
    }

    public function testRewriteSelectWithJoinedUnknownTableThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT)');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM users JOIN unknown_orders ON users.id = unknown_orders.user_id');
    }

    public function testRewriteSelectNoSchemaContextDoesNotThrow(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame('SELECT * FROM users', $plan->sql());
    }

    public function testRewriteAlterTableSetDefaultThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255) DEFAULT NULL)');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ALTER COLUMN name SET DEFAULT 'test'");
    }

    public function testRewriteAlterTableOrderByThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users ORDER BY name");
    }

    public function testRewriteAlterTableConvertToThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4");
    }

    public function testRewriteBuildTableContextWithRowsButNoDefinition(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob', 'extra' => 'data']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('`users` AS', $plan->sql());
        self::assertStringContainsString('AS `id`', $plan->sql());
        self::assertStringContainsString('AS `name`', $plan->sql());
        self::assertStringContainsString('AS `extra`', $plan->sql());
    }

    public function testRewriteBuildTableContextWithDefinitionButNoRows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('`users` AS', $plan->sql());
        self::assertStringContainsString('WHERE 0', $plan->sql());
    }

    public function testRewriteMultipleStatements(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('SELECT 1; SELECT 2');
    }

    public function testRewriteEmptyStatementThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('');
    }

    public function testRewriteAlterTableRenameIndexThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite("ALTER TABLE t RENAME INDEX idx_old TO idx_new");
    }

    public function testRewriteReplaceWithoutColumnsButWithShadowDataSucceeds(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users VALUES (1, 'Bob')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testRewriteReplaceWithoutColumnsButWithDefinitionSucceeds(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users VALUES (1, 'Bob')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testRewriteAlterTableAddPartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ADD PARTITION (PARTITION p1 VALUES LESS THAN (100))');
    }

    public function testRewriteAlterTableDropPartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users DROP PARTITION p1');
    }

    public function testRewriteAlterTableCoalescePartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users COALESCE PARTITION 2');
    }

    public function testRewriteAlterTableAnalyzePartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ANALYZE PARTITION p1');
    }

    public function testRewriteAlterTableCheckPartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users CHECK PARTITION p1');
    }

    public function testRewriteAlterTableOptimizePartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users OPTIMIZE PARTITION p1');
    }

    public function testRewriteAlterTableRebuildPartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users REBUILD PARTITION p1');
    }

    public function testRewriteAlterTableRepairPartitionThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users REPAIR PARTITION p1');
    }

    public function testRewriteAlterTableAddSpatialIndexThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ADD SPATIAL INDEX sp_name (geom)');
    }

    public function testRewriteAlterTableAddConstraintThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ADD CONSTRAINT ck_name CHECK (id > 0)');
    }

    public function testRewriteAlterTableDropConstraintThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users DROP CONSTRAINT ck_name');
    }

    public function testRewriteAlterTableAddKeyThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ADD KEY idx_name (name)');
    }

    public function testRewriteAlterTableDropKeyThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users DROP KEY idx_name');
    }

    public function testRewriteAlterTableRenameKeyThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users RENAME KEY old_idx TO new_idx');
    }

    public function testRewriteAlterTableEngineThrows(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE users ENGINE = InnoDB');
    }

    public function testRewriteSelectWithUnknownTableThrowsWhenSchemaContextExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM unknown_table');
    }

    public function testRewriteSelectWithUnknownJoinTableThrowsWhenSchemaContextExists(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM users JOIN unknown_table ON users.id = unknown_table.user_id');
    }

    public function testBuildTableContextIncludesRegistryDefinitionsNotInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
    }

    public function testRewriteReplaceWithNoColumnsAndNoStoreOrRegistryThrows(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Cannot determine columns');
        $rewriter->rewrite("REPLACE INTO users VALUES (1, 'Bob')");
    }

    public function testRewriteReplaceWithColumnsExplicitDoesNotThrow(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        $plan = $rewriter->rewrite("REPLACE INTO users (id, name) VALUES (1, 'Bob')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
    }

    public function testRewriteReplaceWithStoreDataDoesNotThrow(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users VALUES (1, 'Bob')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
    }

    public function testBuildTableContextColumnsFromStoreRowKeys(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        $sql = $plan->sql();
        self::assertStringContainsString('AS `id`', $sql);
        self::assertStringContainsString('AS `name`', $sql);
    }

    public function testRewriteDeleteEnsuresDmlTablesArePresentInStore(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
    }

    public function testRewriteReplaceWithoutColumnsOrContextThrows(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Cannot determine columns');
        $rewriter->rewrite("REPLACE INTO t VALUES (1, 'Alice')");
    }

    public function testRewriteReplaceWithColumnsSpecifiedDoesNotThrow(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO t (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
    }

    public function testRewriteReplaceWithStoreContextDoesNotThrow(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('t', [['id' => 1, 'name' => 'old']]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO t VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
    }

    public function testRewriteAlterTableOrderByThrowsUnsupported(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT, val INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE t ORDER BY id');
    }

    public function testRewriteAlterTableAddIndexThrowsUnsupported(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT, val INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE t ADD INDEX idx_val (val)');
    }

    public function testRewriteAlterTableDropIndexThrowsUnsupported(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT, val INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE t DROP INDEX idx_val');
    }

    public function testRewriteAlterTableRenameIndexThrowsUnsupported(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT, val INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('ALTER TABLE t RENAME INDEX idx_old TO idx_new');
    }

    public function testRewriteSelectWithJoinDetectsUnknownJoinTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE users (id INT)');
        self::assertNotNull($def);
        $registry->register('users', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        self::expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM users JOIN orders ON users.id = orders.user_id');
    }

    public function testRewriteSelectNoSchemaContextDoesNotCheckUnknownTables(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM nonexistent');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame('SELECT * FROM nonexistent', $plan->sql());
    }

    public function testRewriteBuildTableContextMergesExtraColumnKeysFromRows(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('t', [
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b', 'extra' => 'val'],
        ]);
        $registry = new TableDefinitionRegistry();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM t');
        self::assertStringContainsString('AS `extra`', $plan->sql());
        self::assertStringContainsString('AS `id`', $plan->sql());
        self::assertStringContainsString('AS `name`', $plan->sql());
    }

    public function testRewriteCreateTableAsSelectTransformsSelectPart(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $shadowStore->set('src', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE src (id INT, name VARCHAR(255))');
        self::assertNotNull($def);
        $registry->register('src', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE dest AS SELECT id, name FROM src');
        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertStringContainsString('WITH `src` AS', $plan->sql());
    }

    public function testRewriteTruncateReturnsFalseSelect(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $def = $schemaParser->parse('CREATE TABLE t (id INT)');
        self::assertNotNull($def);
        $registry->register('t', $def);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('TRUNCATE TABLE t');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertSame('SELECT 1 WHERE FALSE', $plan->sql());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(TruncateMutation::class, $plan->mutation());
    }
}
