<?php

declare(strict_types=1);

namespace Tests\Unit;

use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\RewriterContractTest;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteLexicalMasker;
use ZtdQuery\Platform\Sqlite\SqliteMutationResolver;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser;
use ZtdQuery\Platform\Sqlite\SqliteRewriter;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Platform\Sqlite\SqliteViewDefinitionParser;
use ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;

#[CoversClass(SqliteRewriter::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\AddColumnResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\AlteredTableProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\ColumnDefinitionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\DropColumnResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\RenameResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\InsertMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\MutationTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\RowMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\TableMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Alter\AlterOperationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Attach\AttachTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentColumnParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\ValueListParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Insert\InsertClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\ExpressionSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\IdentifierDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\LiteralMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\OpaqueSqlSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\QuotedSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\TopLevelKeywordScanner::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Relation\RelationSourceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Returning\ReturningItemParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementClassifier::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementStructure::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\TargetTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Transaction\TransactionTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Update\UpdateClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ArithmeticExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ComparisonExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionTokenDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\LogicalExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\PrimaryExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Context\ReferencedTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Context\TableContextBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteDependencies::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteHeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CtePrefixMerger::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteReferences::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\FullText\FullTextColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\FullText\MatchExpressionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Index\IndexHintTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\CastTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueExpressionRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueLiteralRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\SqlEdits::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Statement\StatementRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Upsert\UpsertConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Upsert\UpsertExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\ColumnDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableBodyParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableDefinitionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\VirtualTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyEntryParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyTokens::class)]
#[UsesClass(SqliteCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteFullTextSearchRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector::class)]
#[UsesClass(SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteInMemoryAttachStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(SqliteLexicalMasker::class)]
#[UsesClass(SqliteMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteNativeUpsertProjector::class)]
#[UsesClass(SqliteParser::class)]
#[UsesClass(SqliteQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteReadOnlyDiagnosticStatement::class)]
#[UsesClass(SqliteReturningProjectionParser::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteTransactionStatementParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteValueRenderer::class)]
#[UsesClass(SqliteViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteViewShadowRenderer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Transformer\Insert\InsertProjectionBuilder::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Transformer\Select\ShadowCteRenderer::class)]
#[UsesClass(SqliteTransformer::class)]
#[UsesClass(UpdateTransformer::class)]
final class SqliteRewriterTest extends RewriterContractTest
{
    public function testGeneratedExpressionIsPresentBeforeTheFirstShadowWrite(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse(
            'CREATE TABLE orders (qty INTEGER, total INTEGER GENERATED ALWAYS AS (qty * 2) STORED)',
        );
        self::assertNotNull($definition);
        $registry->register('orders', $definition);

        $sql = $this->createRewriter($store, $registry)->rewrite('SELECT total FROM orders')->sql();

        self::assertStringContainsString('(qty * 2) AS "total"', $sql);
    }

    public function testRegisteredViewIsKnownAndMaterialized(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $schemaParser = new SqliteSchemaParser();
        $definition = $schemaParser->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $resolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $views = new ViewDefinitionSet();
        $views->register('active_users', (new SqliteViewDefinitionParser())->fromQuery('SELECT id FROM main.users'));
        $rewriter = new SqliteRewriter(new SqliteQueryGuard($parser), $store, $registry, $transformer, $resolver, $parser, $views);

        $sql = $rewriter->rewrite('SELECT * FROM active_users')->sql();
        self::assertStringStartsWith('WITH "users" AS', $sql);
        self::assertStringContainsString('"active_users" AS (SELECT id FROM users)', $sql);

        $viewOnlyStore = new ShadowStore();
        $viewOnlyRegistry = new TableDefinitionRegistry();
        $viewOnlyViews = new ViewDefinitionSet();
        $viewOnlyViews->register('constant_view', (new SqliteViewDefinitionParser())->fromQuery('SELECT 1 AS id'));
        $viewOnlyResolver = new SqliteMutationResolver($viewOnlyStore, $viewOnlyRegistry, $schemaParser, $parser);
        $viewOnlyRewriter = new SqliteRewriter(new SqliteQueryGuard($parser), $viewOnlyStore, $viewOnlyRegistry, $transformer, $viewOnlyResolver, $parser, $viewOnlyViews);

        $this->expectException(UnknownSchemaException::class);
        $viewOnlyRewriter->rewrite('SELECT * FROM missing_table');
    }

    public function testCteReferencesAreMatchedCaseInsensitivelyDuringSchemaValidation(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse('CREATE TABLE known_table (id INTEGER PRIMARY KEY)');
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
        $definition = $this->createSchemaParser()->parse('CREATE TABLE known_table (id INTEGER PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('known_table', $definition);

        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessage('missing_table');

        $this->createRewriter(new ShadowStore(), $registry)->rewrite(
            'WITH users AS (SELECT 1 AS id) SELECT * FROM Users JOIN missing_table ON TRUE',
        );
    }

    public function testInMemoryAttachPassesThroughUnchanged(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $sql = "ATTACH DATABASE ':memory:' AS db2";

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testPersistentAttachRemainsUnsupported(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());

        $this->expectException(UnsupportedSqlException::class);

        $rewriter->rewrite("ATTACH 'test.sqlite' AS db2");
    }

    public function testSchemaQualifiedSelectUsesShadowCte(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $definition = $this->createSchemaParser()->parse($this->usersCreateTableSql());
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $plan = $this->createRewriter($store, $registry)->rewrite('SELECT name FROM main.users');

        self::assertStringStartsWith('WITH "users" AS', $plan->sql());
        self::assertStringEndsWith('SELECT name FROM users', $plan->sql());
    }

    public function testReadOnlyDiagnosticsPassThroughUnchanged(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $sql = 'EXPLAIN QUERY PLAN SELECT * FROM users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    #[Override]
    protected function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry): SqliteRewriter
    {
        $parser = new SqliteParser();
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);

        return new SqliteRewriter(new SqliteQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);
    }

    #[Override]
    protected function createSchemaParser(): SchemaParser
    {
        return new SqliteSchemaParser();
    }

    #[Override]
    protected function selectSql(): string
    {
        return 'SELECT id, name, email FROM users WHERE id = 1';
    }

    #[Override]
    protected function insertSql(): string
    {
        return "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')";
    }

    #[Override]
    protected function updateSql(): string
    {
        return "UPDATE users SET name = 'Bob' WHERE id = 1";
    }

    #[Override]
    protected function deleteSql(): string
    {
        return 'DELETE FROM users WHERE id = 1';
    }

    #[Override]
    protected function createTableSql(): string
    {
        return 'CREATE TABLE orders (id INTEGER PRIMARY KEY, amount REAL)';
    }

    #[Override]
    protected function dropTableSql(): string
    {
        return 'DROP TABLE IF EXISTS orders';
    }

    #[Override]
    protected function unsupportedSql(): string
    {
        return 'CREATE INDEX idx ON users (name)';
    }

    #[Override]
    protected function usersCreateTableSql(): string
    {
        return 'CREATE TABLE users (id INTEGER PRIMARY KEY NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL)';
    }

    #[Override]
    public function testSelectReturnsReadKind(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
        self::assertStringContainsString('SELECT', strtoupper($plan->sql()));
    }

    #[Override]
    public function testInsertReturnsWriteSimulatedWithMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql());
    }

    #[Override]
    public function testUpdateReturnsWriteSimulatedWithMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertStringContainsString('"users"."id" AS "__ztd_original_id"', $plan->sql());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql());
    }

    #[Override]
    public function testDeleteReturnsWriteSimulatedWithMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql());
    }

    public function testDeleteFromWithoutWhereReturnsWriteSimulated(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
    }

    public function testReplaceReturnsWriteSimulatedWithMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("REPLACE INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testInsertOrReplaceReturnsWriteSimulatedWithReplaceMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT OR REPLACE INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(ReplaceMutation::class, $plan->mutation());
    }

    public function testInsertOnConflictReturnsUpsertMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com') ON CONFLICT (id) DO UPDATE SET name = excluded.name");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(UpsertMutation::class, $plan->mutation());
        self::assertStringContainsString('__ztd_upsert_value_0', $plan->sql());
        self::assertStringNotContainsString('excluded.', $plan->sql());
    }

    #[Override]
    public function testCreateTableReturnsDdlSimulated(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(CreateTableMutation::class, $plan->mutation());
    }

    #[Override]
    public function testDropTableReturnsDdlSimulated(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DROP TABLE users');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(DropTableMutation::class, $plan->mutation());
    }

    #[Override]
    public function testUnsupportedSqlThrowsException(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('CREATE INDEX idx ON users (name)');
    }

    #[Override]
    public function testEmptyInputThrowsException(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('');
    }

    public function testMultiStatementThrowsException(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('SELECT 1; SELECT 2');
    }

    #[Override]
    public function testRewriteIsDeterministic(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan1 = $rewriter->rewrite('SELECT * FROM users');
        $plan2 = $rewriter->rewrite('SELECT * FROM users');

        self::assertSame($plan1->sql(), $plan2->sql());
        self::assertSame($plan1->kind(), $plan2->kind());
    }

    #[Override]
    public function testReadPlanHasNoMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
    }

    #[Override]
    public function testWritePlanHasNonNullMutation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com')");

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
    }

    public function testInsertUsesDefaultsFromRegistryWithoutShadowRows(): void
    {
        $definition = (new SqliteSchemaParser())->parse("CREATE TABLE settings (id INTEGER, label TEXT DEFAULT 'new')");
        self::assertNotNull($definition);
        $registry = new TableDefinitionRegistry();
        $registry->register('settings', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('INSERT INTO settings (id) VALUES (1)');

        self::assertStringContainsString("'new'", $plan->sql());
        self::assertStringContainsString('AS "label"', $plan->sql());
    }

    public function testInsertUsesIdentityStrategyFromRegistryWithoutShadowRows(): void
    {
        $definition = (new SqliteSchemaParser())->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite("INSERT INTO users (name) VALUES ('Alice')");

        self::assertStringContainsString('CAST(1 AS INTEGER) AS "id"', $plan->sql());
    }

    public function testRewriteMultiple(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $multiPlan = $rewriter->rewriteMultiple("SELECT * FROM users; INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'a@b.com')");

        self::assertSame(2, $multiPlan->count());
        self::assertSame(QueryKind::READ, $multiPlan->get(0)?->kind());
        self::assertSame(QueryKind::WRITE_SIMULATED, $multiPlan->get(1)?->kind());
    }

    public function testSelectWithShadowDataIncludesCte(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name', 'email'],
            ['id' => 'INTEGER', 'name' => 'TEXT', 'email' => 'TEXT'],
            ['id'],
            ['id', 'name'],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1, 'name' => 'Alice', 'email' => 'a@b.com']]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');

        self::assertStringStartsWith('WITH', $plan->sql());
        self::assertStringContainsString('"users"', $plan->sql());
        self::assertStringContainsString('SELECT', strtoupper($plan->sql()));
    }

    public function testSelectUnknownTableWithSchemaContextThrows(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM nonexistent');
    }

    public function testSelectKnownTableNoSchemaContextDoesNotThrow(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM whatever');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testSelectWithShadowStoreOnlyHasSchemaContext(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('WITH', $plan->sql());
    }

    public function testUpdateEnsuresShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testDeleteFromWithoutWhereReturnsSqlWithSelectWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users');
        self::assertStringContainsString('FROM "users"', $plan->sql());
    }

    public function testDdlSimulatedReturnsSqlSelectWhere0(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        self::assertSame('SELECT 1 WHERE 0', $plan->sql());
        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
    }

    public function testAlterTableUsesShadowedMigrationSelect(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('people', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->set('people', [['id' => 1, 'name' => 'Alice']]);
        $rewriter = $this->createRewriter($store, $registry);

        $plan = $rewriter->rewrite('ALTER TABLE people ADD COLUMN age INTEGER DEFAULT 7');

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
        self::assertInstanceOf(AlterTableMutation::class, $plan->mutation());
        self::assertStringContainsString('WITH "people" AS', $plan->sql());
        self::assertStringContainsString('SELECT "id", "name", 7 AS "age" FROM "people"', $plan->sql());
    }

    public function testRemovedTableIsShadowedInsteadOfFallingThrough(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('people', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $registry->markRemoved('people');
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('SELECT id, name FROM people');

        self::assertStringContainsString('WITH "people" AS', $plan->sql());
        self::assertStringContainsString('WHERE 0', $plan->sql());
    }

    public function testRewriteMultipleEmptyThrows(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewriteMultiple('');
    }

    public function testSelectWithRegistryOnlyBuildTableContext(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('WITH', $plan->sql());
        self::assertStringContainsString('WHERE 0', $plan->sql());
    }

    public function testSelectWithShadowDataColumnsInferred(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertStringContainsString('"users"', $plan->sql());
        self::assertStringContainsString('"id"', $plan->sql());
        self::assertStringContainsString('"name"', $plan->sql());
    }

    public function testDeleteWithWhereProducesTransformedSql(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertStringContainsString('SELECT', $plan->sql());
        self::assertStringContainsString('WHERE id = 1', $plan->sql());
    }

    public function testDeleteEnsuresShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
    }

    public function testSelectExistingInShadowStoreIsNotUnknown(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testInsertWithShadowDataProducesTransformedSql(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (2, 'Bob')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertStringContainsString('SELECT', $plan->sql());
        self::assertNotNull($plan->mutation());
    }

    public function testBuildTableContextMergesColumnsFromMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $store->insert('users', [['id' => 2, 'name' => 'Bob', 'email' => 'b@b.com']]);
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertStringContainsString('"id"', $plan->sql());
        self::assertStringContainsString('"name"', $plan->sql());
        self::assertStringContainsString('"email"', $plan->sql());
    }

    public function testBuildTableContextUsesDefinitionColumns(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1]]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertStringContainsString('"name"', $plan->sql());
    }

    public function testBuildTableContextRegistryOnlyTableIncluded(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $registry->register('orders', new TableDefinition(
            ['id', 'amount'],
            ['id' => 'INTEGER', 'amount' => 'REAL'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertStringContainsString('"users"', $plan->sql());
    }

    public function testSelectTableInShadowStoreNotUnknown(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $registry = new TableDefinitionRegistry();
        $registry->register('orders', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM nonexistent');
    }

    public function testDeleteFromQuotedTableWithoutWhereReturnsSqlWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('my_table', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('my_table');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM "my_table"');
        self::assertStringContainsString('FROM "my_table"', $plan->sql());
    }

    public function testDeleteFromWithSemicolonAndWhitespace(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users ;');
        self::assertStringContainsString('FROM "users"', $plan->sql());
    }

    public function testUpdateEnsuresShadowStoreCalledOnTarget(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            ['id' => 'INTEGER', 'val' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE t SET val = 'x' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertSame([], $store->get('t'));
    }

    public function testDeleteEnsuresShadowStoreCalledOnTarget(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id', 'val'],
            ['id' => 'INTEGER', 'val' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM t WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertSame([], $store->get('t'));
    }

    public function testBuildTableContextShadowStoreEmptyRowsNoDefinitionPassesThrough(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT 1');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testHasSchemaContextWithRegistryOnly(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM nonexistent');
    }

    public function testHasSchemaContextWithShadowStoreOnly(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');
        $store->insert('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnknownSchemaException::class);
        $rewriter->rewrite('SELECT * FROM nonexistent');
    }

    public function testDeleteFromBacktickQuotedTableReturnsSqlWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('t');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM `t`');
        self::assertStringContainsString('FROM "t"', $plan->sql());
    }

    public function testDeleteFromBracketQuotedTableReturnsSqlWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('t', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('t');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM [t]');
        self::assertStringContainsString('FROM "t"', $plan->sql());
    }

    public function testUpdateEnsuresShadowStoreForTargetTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertSame([], $store->get('users'));
    }

    public function testDeleteEnsuresShadowStoreForTargetTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
    }

    public function testDeleteFromLowercaseReturnsSqlWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('delete from users');
        self::assertStringContainsString('FROM "users"', $plan->sql());
    }

    public function testBuildTableContextIncludesMultipleTablesFromRegistry(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $registry->register('orders', new TableDefinition(
            ['oid', 'uid'],
            ['oid' => 'INTEGER', 'uid' => 'INTEGER'],
            ['oid'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users JOIN orders ON users.id = orders.uid');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testBuildTableContextWithShadowStoreColumnsInferred(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();
        $store->ensure('users');
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('WITH', $plan->sql());
        self::assertStringContainsString('"id"', $plan->sql());
        self::assertStringContainsString('"name"', $plan->sql());
    }

    public function testBuildTableContextSkipsAlreadyAddedFromShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('WITH', $plan->sql());
        self::assertSame(1, substr_count($plan->sql(), '"users" AS'));
    }

    public function testInsertDoesNotEnsureShadowStoreForTargetTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name) VALUES (1, 'Alice')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
    }

    public function testDeleteFromWithCommentsReturnsSqlWhere0(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('users');
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('/* comment */ DELETE FROM users');
        self::assertStringContainsString('FROM "users"', $plan->sql());
    }

    public function testSelectRewritesTableAfterBlockComment(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $rewriter = $this->createRewriter($store, $registry);

        $plan = $rewriter->rewrite('SELECT * FROM/* table */users');

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('"users" AS', $plan->sql());
    }

    public function testSelectIgnoresSqlKeywordsInsideLeadingLineComment(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $rewriter = $this->createRewriter($store, $registry);
        $sql = "-- SELECT * FROM other_table WHERE DELETE UPDATE INSERT\nSELECT * FROM users";

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('"users" AS', $plan->sql());
        self::assertStringNotContainsString('"other_table" AS', $plan->sql());
    }

    public function testInsertResolvesTargetAfterBlockComment(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('INSERT INTO/* table */users (id) VALUES (1)');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testUpdateResolvesTargetAfterBlockComment(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('UPDATE/* table */users SET id = 2 WHERE id = 1');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testDeleteResolvesTargetAfterBlockComment(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);

        $plan = $rewriter->rewrite('DELETE FROM/* table */users WHERE id = 1');

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testUpdateEnsuresShadowStoreEntry(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertArrayHasKey('users', $store->getAll());
    }

    public function testDeleteEnsuresShadowStoreEntry(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertArrayHasKey('users', $store->getAll());
    }

    public function testSelectWithMultipleRegisteredTablesIncludesAll(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $registry->register('orders', new TableDefinition(
            ['id', 'user_id'],
            ['id' => 'INTEGER', 'user_id' => 'INTEGER'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users JOIN orders ON users.id = orders.user_id');
        self::assertSame(QueryKind::READ, $plan->kind());
        $sql = $plan->sql();
        self::assertStringContainsString('"users"', $sql);
        self::assertStringContainsString('"orders"', $sql);
    }

    public function testSelectWithShadowStoreAndRegistryTablesMerged(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            [],
            [],
        ));
        $store = new ShadowStore();
        $store->ensure('orders');
        $store->set('orders', [['id' => 1, 'user_id' => 1]]);
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $schemaParser = new SqliteSchemaParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $mutationResolver = new SqliteMutationResolver($store, $registry, $schemaParser, $parser);
        $rewriter = new SqliteRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM users JOIN orders ON users.id = orders.user_id');
        self::assertSame(QueryKind::READ, $plan->kind());
        $sql = $plan->sql();
        self::assertStringContainsString('"users"', $sql);
        self::assertStringContainsString('"orders"', $sql);
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
    public function testTransactionStatementRestoresSnapshotOnRollback(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $rewriter = $this->createRewriter($store, new TableDefinitionRegistry());
        $transactions = new \ZtdQuery\Shadow\ShadowTransactionManager($store);
        $begin = $rewriter->transactionStatement('BEGIN');
        $rollback = $rewriter->transactionStatement('ROLLBACK');
        self::assertNotNull($begin);
        self::assertNotNull($rollback);
        $begin->apply($transactions);
        $store->set('users', [['id' => 2]]);
        $rollback->apply($transactions);
        self::assertSame([['id' => 1]], $store->get('users'));
        self::assertNull($rewriter->transactionStatement('SELECT 1'));
    }

    public function testSplitStatementsPreservesQuotedSemicolons(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        self::assertSame(["SELECT 'a;b'", 'SELECT 2'], $rewriter->splitStatements("SELECT 'a;b'; SELECT 2;"));
    }

    public function testEmptyResultSelectProducesNoRows(): void
    {
        $rewriter = $this->createRewriter(new ShadowStore(), new TableDefinitionRegistry());
        $statement = (new PDO('sqlite::memory:'))->query($rewriter->emptyResultSelect());
        self::assertNotFalse($statement);
        self::assertSame([], $statement->fetchAll());
    }

    public function testCommitRewriteStateKeepsGeneratedIdentitiesAcrossRewrites(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = (new SqliteSchemaParser())->parse('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $rewriter = $this->createRewriter(new ShadowStore(), $registry);
        $first = $rewriter->rewrite("INSERT INTO users(name) VALUES ('Alice')");
        $rewriter->commitRewriteState();
        $second = $rewriter->rewrite("INSERT INTO users(name) VALUES ('Bob')");
        self::assertStringContainsString('CAST(1 AS INTEGER) AS "id"', $first->sql());
        self::assertStringContainsString('CAST(2 AS INTEGER) AS "id"', $second->sql());
    }

}
