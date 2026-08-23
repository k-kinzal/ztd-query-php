<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\RewriterContractTest;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlLoadDataProjector;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlPartitioningParser;
use ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\MySqlViewDefinitionParser;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\UpdateSourceExtractor;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;
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
use ZtdQuery\Shadow\ShadowTableState;

#[CoversClass(MySqlRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser::class)]
#[UsesClass(MySqlLoadDataProjector::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(MySqlPartitioningParser::class)]
#[UsesClass(MySqlPartitionSelectionRewriter::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTransactionStatementParser::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(UpdateAssignmentExtractor::class)]
#[UsesClass(UpdateSourceExtractor::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewShadowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlRewriterTest extends RewriterContractTest
{
    public function testPartitionSelectionUsesRegisteredPartitionMetadata(): void
    {
        $store = new ShadowStore();
        $store->set('events', [
            ['id' => 1, 'event_date' => '2023-06-01'],
            ['id' => 2, 'event_date' => '2024-06-01'],
        ]);
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse(
            'CREATE TABLE events (id INT, event_date DATE) '
            . 'PARTITION BY RANGE (YEAR(event_date)) ('
            . 'PARTITION p2023 VALUES LESS THAN (2024), '
            . 'PARTITION p2024 VALUES LESS THAN (2025), '
            . 'PARTITION pmax VALUES LESS THAN MAXVALUE)',
        );
        self::assertNotNull($definition);
        $registry->register('events', $definition);

        $sql = $this->createRewriter($store, $registry)
            ->rewrite('SELECT id FROM events PARTITION (p2024)')
            ->sql();

        self::assertStringStartsWith('WITH `events` AS', $sql);
        self::assertStringContainsString(
            'FROM (SELECT * FROM events WHERE ((YEAR(event_date)) >= 2024 AND (YEAR(event_date)) < 2025)) AS events',
            $sql,
        );
    }

    public function testPartitionSelectionUsesDefinitionWithoutMaterializedRows(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse(
            'CREATE TABLE events (id INT) PARTITION BY RANGE (id) ('
            . 'PARTITION p0 VALUES LESS THAN (10), PARTITION pmax VALUES LESS THAN MAXVALUE)',
        );
        self::assertNotNull($definition);
        $registry->register('events', $definition);

        $sql = $this->createRewriter(new ShadowStore(), $registry)
            ->rewrite('SELECT id FROM events PARTITION (p0)')
            ->sql();

        self::assertStringContainsString('FROM (SELECT * FROM events WHERE ((id) IS NULL OR (id) < 10)) AS events', $sql);
    }


    public function testGeneratedExpressionIsPresentBeforeTheFirstShadowWrite(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse(
            'CREATE TABLE orders (qty INT, total INT GENERATED ALWAYS AS (qty * 2) STORED)',
        );
        self::assertNotNull($definition);
        $registry->register('orders', $definition);

        $sql = $this->createRewriter($store, $registry)->rewrite('SELECT total FROM orders')->sql();

        self::assertStringContainsString('(qty * 2) AS `total`', $sql);
    }

    public function testRegisteredViewIsKnownAndMaterialized(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $resolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $views = new ViewDefinitionSet();
        $views->register('active_users', (new MySqlViewDefinitionParser())->fromQuery('SELECT id FROM app.users'));
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $resolver, $parser, $views);

        $sql = $rewriter->rewrite('SELECT * FROM active_users')->sql();
        self::assertStringStartsWith('WITH `users` AS', $sql);
        self::assertStringContainsString('`active_users` AS (SELECT id FROM users)', $sql);

        $viewOnlyStore = new ShadowStore();
        $viewOnlyRegistry = new TableDefinitionRegistry();
        $viewOnlyViews = new ViewDefinitionSet();
        $viewOnlyViews->register('constant_view', (new MySqlViewDefinitionParser())->fromQuery('SELECT 1 AS id'));
        $viewOnlyResolver = new MySqlMutationResolver($viewOnlyStore, $viewOnlyRegistry, $schemaParser, $updateTransformer, $deleteTransformer);
        $viewOnlyRewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $viewOnlyStore, $viewOnlyRegistry, $transformer, $viewOnlyResolver, $parser, $viewOnlyViews);

        $this->expectException(UnknownSchemaException::class);
        $viewOnlyRewriter->rewrite('SELECT * FROM missing_table');
    }

    public function testDatabaseQualifiedSelectUsesShadowCte(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $plan = $this->createRewriter($store, $registry)->rewrite('SELECT name FROM app.users');

        self::assertStringStartsWith('WITH `users` AS', $plan->sql());
        self::assertStringEndsWith('SELECT name FROM users', $plan->sql());
    }

    public function testExplainPassesThroughUnchanged(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $sql = 'EXPLAIN SELECT * FROM users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testDescribePassesThroughUnchanged(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $sql = 'DESCRIBE users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testShowCreateTablePassesThroughUnchanged(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $sql = 'SHOW CREATE TABLE users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testDerivedTableKeepsNestedPhysicalRelationInShadowScope(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $plan = $this->createRewriter($store, $registry)->rewrite('SELECT * FROM (SELECT id, name FROM users) AS selected');

        self::assertStringStartsWith('WITH `users` AS', $plan->sql());
    }

    public function testHashCommentDoesNotCreateAPhantomSelectSource(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $plan = $this->createRewriter($store, $registry)->rewrite("# SELECT * FROM unknown_table\nSELECT * FROM users");

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('FROM users', $plan->sql());
    }

    public function testCteReferencesAreMatchedCaseInsensitivelyDuringSchemaValidation(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse('CREATE TABLE known_table (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('known_table', $definition);

        $plan = $this->createRewriter(new ShadowStore(), $registry)->rewrite(
            'WITH users AS (SELECT 1 AS id) SELECT * FROM Users',
        );

        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testUnknownTableAfterDeclaredCteIsRejected(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse('CREATE TABLE known_table (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('known_table', $definition);

        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessage('missing_table');

        $this->createRewriter(new ShadowStore(), $registry)->rewrite(
            'WITH users AS (SELECT 1 AS id) SELECT * FROM Users JOIN missing_table ON TRUE',
        );
    }

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

    public function testRewritesSingleSetExpressionsWithoutTreatingThemAsMultipleStatements(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());

        $except = $rewriter->rewrite('SELECT 1 EXCEPT SELECT 2');
        $intersect = $rewriter->rewrite('SELECT 1 INTERSECT SELECT 2');
        $caseExists = $rewriter->rewrite('SELECT 1 WHERE CASE WHEN EXISTS(SELECT 1) THEN TRUE ELSE FALSE END');

        self::assertSame(QueryKind::READ, $except->kind());
        self::assertSame('SELECT 1 EXCEPT SELECT 2', $except->sql());
        self::assertSame(QueryKind::READ, $intersect->kind());
        self::assertSame('SELECT 1 INTERSECT SELECT 2', $intersect->sql());
        self::assertSame(QueryKind::READ, $caseExists->kind());
    }

    public function testCompositeReadRejectsSelectInto(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Statement type not supported');

        $rewriter->rewrite('SELECT 1 INTO @result EXCEPT SELECT 2');
    }

    public function testCompositeReadAllowsTablesWithoutSchemaContext(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());

        $plan = $rewriter->rewrite('SELECT * FROM missing EXCEPT SELECT * FROM other');

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame('SELECT * FROM missing EXCEPT SELECT * FROM other', $plan->sql());
    }

    public function testCompositeReadRejectsUnknownTableWithSchemaContext(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE known (id INT)');
        self::assertNotNull($definition);
        $registry->register('known', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessage('unknown_table');

        $rewriter->rewrite('SELECT * FROM known EXCEPT SELECT * FROM unknown_table');
    }

    public function testRewriteMultipleUsesLexicalStatementBoundaries(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());

        $plan = $rewriter->rewriteMultiple('SELECT 1 EXCEPT SELECT 2; SELECT 3');

        self::assertCount(2, $plan->plans());
        self::assertSame('SELECT 1 EXCEPT SELECT 2', $plan->plans()[0]->sql());
        self::assertSame('SELECT 3', $plan->plans()[1]->sql());
    }

    public function testRewriteUpdateCreatesMutation(): void
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

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertStringContainsString('`users`.`id` AS `__ztd_original_id`', $plan->sql());
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

    public function testRewriteInsertUsesDefaultsFromRegistryWithoutShadowRows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $definition = $schemaParser->parse("CREATE TABLE settings (id INT, label VARCHAR(20) DEFAULT 'new')");
        self::assertNotNull($definition);
        $registry = new TableDefinitionRegistry();
        $registry->register('settings', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('INSERT INTO settings (id) VALUES (1)');

        self::assertStringContainsString("'new'", $plan->sql());
        self::assertStringContainsString('AS `label`', $plan->sql());
    }

    public function testRewriteInsertUsesIdentityStrategyFromRegistryWithoutShadowRows(): void
    {
        $schemaParser = new MySqlSchemaParser(new MySqlParser());
        $definition = $schemaParser->parse('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20))');
        self::assertNotNull($definition);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite("INSERT INTO users (name) VALUES ('Alice')");

        self::assertStringContainsString('CAST(1 AS SIGNED) AS `id`', $plan->sql());
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
        self::assertStringContainsString('__ztd_upsert_value_0', $plan->sql());
    }

    public function testRewriteUpsertUsesCandidateKeysForStoredTableContext(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->ensure('users');
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer(
            $parser,
            $selectTransformer,
            $insertTransformer,
            $updateTransformer,
            $deleteTransformer,
            $replaceTransformer,
        );
        $resolver = new MySqlMutationResolver(
            $shadowStore,
            $registry,
            $schemaParser,
            $updateTransformer,
            $deleteTransformer,
        );
        $rewriter = new MySqlRewriter(
            new MySqlQueryGuard($parser),
            $shadowStore,
            $registry,
            $transformer,
            $resolver,
            $parser,
        );

        $plan = $rewriter->rewrite(
            "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = VALUES(name)",
        );

        self::assertStringContainsString('`__ztd_incoming`.`name`', $plan->sql());
        self::assertStringContainsString('__ztd_upsert_value_0', $plan->sql());
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

    public function testRewriteWithStatementInsertResolvesItsInnerMutation(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("WITH source AS (SELECT 1 AS id, 'Alice' AS name) INSERT INTO users SELECT * FROM source");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertStringStartsWith('WITH source AS', $plan->sql());
        self::assertSame(1, substr_count($plan->sql(), 'WITH'));
    }

    public function testRewriteWithStatementDeleteIgnoresHashCommentsAroundItsCteHeader(): void
    {
        $shadowStore = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $shadowStore, $registry, $transformer, $mutationResolver, $parser);
        $sql = <<<'SQL'
            WITH RECURSIVE # modifier
            chosen AS (SELECT 1 AS id) # body
            DELETE # target
            FROM users WHERE id IN (SELECT id FROM chosen)
            SQL;

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertStringContainsString('SELECT', $plan->sql());
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

    public function testUpdateDoesNotPromoteMaterializedUnknownTable(): void
    {
        $store = new ShadowStore();
        $store->insert('late_table', [['id' => 1, 'name' => 'Alice']]);
        $rewriter = $this->createRewriter($store, new TableDefinitionRegistry());

        try {
            $rewriter->rewrite("UPDATE late_table SET name = 'Bob' WHERE id = 1");
            self::fail('Expected an unknown schema exception.');
        } catch (UnknownSchemaException) {
            self::assertSame(ShadowTableState::Materialized, $store->state('late_table'));
        }
    }
}
