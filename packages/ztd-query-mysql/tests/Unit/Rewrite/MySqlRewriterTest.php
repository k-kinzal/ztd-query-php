<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Rewrite\LoadData\MySqlLoadDataProjector;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Schema\Partition\MySqlPartitioningParser;
use ZtdQuery\Platform\MySql\Schema\View\MySqlViewDefinitionParser;
use ZtdQuery\Platform\MySql\Shadow\Mutation\Table\AlterTableMutation;
use ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Sql\Dml\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\Mutation\Row\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\Table\DropTableMutation;
use ZtdQuery\Shadow\Mutation\Table\TruncateMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;

#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAction::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAlteration::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\CreateTableRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\OperationApplier::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\PrimaryKeyAlteration::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\StoredColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\UnsupportedKeyword::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Row\RowMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\TableMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\TargetName::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Alter\OptionList::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\AssignmentExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Diagnostic\DiagnosticKeywords::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\OptionalInsertIntoNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ExpressionNames::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ReferenceReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Transaction\TokenReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\AssignmentReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\ExpressionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\LiteralReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\StringLiteral::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\ExpressionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\ColumnMapping::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\InputFile::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\InputFormat::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\InsertQuery::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\RecordParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\RowProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SelectionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SourceProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MetadataColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\QualifiedColumn::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Classification\CteStatementKind::class)]

#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard::class)]

#[UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\DefinitionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\TokenReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ReplaceStatementConverter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ResultProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Select\ExpressionAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\OrderRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\ValueNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Shadow\CteRows::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\RankEdits::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[CoversClass(MySqlRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\MySqlUpsertExpressionParser::class)]
#[UsesClass(MySqlLoadDataProjector::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[UsesClass(MySqlPartitioningParser::class)]
#[UsesClass(MySqlPartitionSelectionRewriter::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Diagnostic\MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Transaction\MySqlTransactionStatementParser::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(UpdateAssignmentExtractor::class)]
#[UsesClass(UpdateSourceExtractor::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MySqlNativeUpsertProjector::class)]
#[UsesClass(MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\View\MySqlViewShadowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Rewrite\StatementRewriter::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Rewrite\Context\TableContext::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns::class)]
final class MySqlRewriterTest extends TestCase
{
    public function testPartitionSelectionUsesRegisteredPartitionMetadata(): void
    {
        $store = new ShadowStore();
        $store->set('events', [
            ['id' => 1, 'event_date' => '2023-06-01'],
            ['id' => 2, 'event_date' => '2024-06-01'],
        ]);
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(
            'CREATE TABLE events (id INT, event_date DATE) '
            . 'PARTITION BY RANGE (YEAR(event_date)) ('
            . 'PARTITION p2023 VALUES LESS THAN (2024), '
            . 'PARTITION p2024 VALUES LESS THAN (2025), '
            . 'PARTITION pmax VALUES LESS THAN MAXVALUE)',
        );
        self::assertNotNull($definition);
        $registry->register('events', $definition);

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $sql = $rewriter
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
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(
            'CREATE TABLE events (id INT) PARTITION BY RANGE (id) ('
            . 'PARTITION p0 VALUES LESS THAN (10), PARTITION pmax VALUES LESS THAN MAXVALUE)',
        );
        self::assertNotNull($definition);
        $registry->register('events', $definition);

        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $sql = $rewriter
            ->rewrite('SELECT id FROM events PARTITION (p0)')
            ->sql();

        self::assertStringContainsString('FROM (SELECT * FROM events WHERE ((id) IS NULL OR (id) < 10)) AS events', $sql);
    }

    public function testGeneratedExpressionIsPresentBeforeTheFirstShadowWrite(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(
            'CREATE TABLE orders (qty INT, total INT GENERATED ALWAYS AS (qty * 2) STORED)',
        );
        self::assertNotNull($definition);
        $registry->register('orders', $definition);

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $sql = $rewriter->rewrite('SELECT total FROM orders')->sql();

        self::assertStringContainsString('(qty * 2) AS `total`', $sql);
    }

    public function testRegisteredViewIsKnownAndMaterialized(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)
SQL);
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
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT name FROM app.users');

        self::assertStringStartsWith('WITH `users` AS', $plan->sql());
        self::assertStringEndsWith('SELECT name FROM users', $plan->sql());
    }

    public function testExplainPassesThroughUnchanged(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $sql = 'EXPLAIN SELECT * FROM users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testDescribePassesThroughUnchanged(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $sql = 'DESCRIBE users';

        $plan = $rewriter->rewrite($sql);

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($sql, $plan->sql());
    }

    public function testShowCreateTablePassesThroughUnchanged(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

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
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT * FROM (SELECT id, name FROM users) AS selected');

        self::assertStringStartsWith('WITH `users` AS', $plan->sql());
    }

    public function testHashCommentDoesNotCreateAPhantomSelectSource(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        self::assertNotNull($definition);
        $registry->register('users', $definition);

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("# SELECT * FROM unknown_table\nSELECT * FROM users");

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertStringContainsString('FROM users', $plan->sql());
    }

    public function testCteReferencesAreMatchedCaseInsensitivelyDuringSchemaValidation(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse('CREATE TABLE known_table (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('known_table', $definition);

        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite(
            'WITH users AS (SELECT 1 AS id) SELECT * FROM Users',
        );

        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testUnknownTableAfterDeclaredCteIsRejected(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse('CREATE TABLE known_table (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('known_table', $definition);

        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessage('missing_table');

        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $rewriter->rewrite(
            'WITH users AS (SELECT 1 AS id) SELECT * FROM Users JOIN missing_table ON TRUE',
        );
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
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


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
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Statement type not supported');

        $rewriter->rewrite('SELECT 1 INTO @result EXCEPT SELECT 2');
    }

    public function testCompositeReadAllowsTablesWithoutSchemaContext(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


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
        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessage('unknown_table');

        $rewriter->rewrite('SELECT * FROM known EXCEPT SELECT * FROM unknown_table');
    }

    public function testRewriteMultipleUsesLexicalStatementBoundaries(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


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
        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


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
        $store = new ShadowStore();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


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

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
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
        $rewriter->rewrite('ALTER TABLE users ALTER COLUMN name DROP DEFAULT');
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
        $rewriter->rewrite('ALTER TABLE users ORDER BY name');
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
        $rewriter->rewrite('ALTER TABLE users ADD INDEX idx_name (name)');
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
        $rewriter->rewrite('ALTER TABLE users DROP INDEX idx_name');
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
        $rewriter->rewrite('ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4');
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
        $rewriter->rewrite('ALTER TABLE users ADD FULLTEXT INDEX ft_name (name)');
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
        $rewriter->rewrite('ALTER TABLE users ORDER BY name');
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
        $rewriter->rewrite('ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4');
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
        $rewriter->rewrite('ALTER TABLE t RENAME INDEX idx_old TO idx_new');
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
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);


        try {
            $rewriter->rewrite("UPDATE late_table SET name = 'Bob' WHERE id = 1");
            self::fail('Expected an unknown schema exception.');
        } catch (UnknownSchemaException) {
            self::assertSame(ShadowTableState::Materialized, $store->state('late_table'));
        }
    }

    public function testSplitStatements(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        self::assertSame(['SELECT 1', "SELECT ';'"], $rewriter->splitStatements("SELECT 1; SELECT ';'"));
    }

    public function testTransactionStatement(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        self::assertNotNull($rewriter->transactionStatement('BEGIN'));
        self::assertNull($rewriter->transactionStatement('SELECT 1'));
    }

    public function testEmptyResultSelect(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        self::assertSame('SELECT 1 WHERE FALSE', $rewriter->emptyResultSelect());
    }

    public function testCommitRewriteState(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $first = $rewriter->rewrite("INSERT INTO users (name) VALUES ('a')");
        $rewriter->commitRewriteState();
        $second = $rewriter->rewrite("INSERT INTO users (name) VALUES ('b')");
        self::assertStringContainsString('CAST(1 AS SIGNED) AS `id`', $first->sql());
        self::assertStringContainsString('CAST(2 AS SIGNED) AS `id`', $second->sql());
    }

    public function testSelectReturnsReadKind(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT id, name, email FROM users WHERE id = 1');
        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testInsertReturnsWriteSimulatedWithMutation(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testUpdateReturnsWriteSimulatedWithMutation(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("UPDATE users SET name = 'Bob' WHERE id = 1");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testDeleteReturnsWriteSimulatedWithMutation(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    public function testCreateTableReturnsDdlSimulated(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('CREATE TABLE orders (id INT PRIMARY KEY, amount DECIMAL(10,2))');
        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
    }

    public function testDropTableReturnsDdlSimulated(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('DROP TABLE IF EXISTS orders');
        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
    }

    public function testUnsupportedSqlThrowsException(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('CREATE DATABASE test_db');
    }

    public function testEmptyInputThrowsException(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $this->expectException(UnsupportedSqlException::class);
        $rewriter->rewrite('');
    }

    public function testRewriteIsDeterministic(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan1 = $rewriter->rewrite('SELECT id, name, email FROM users WHERE id = 1');
        $plan2 = $rewriter->rewrite('SELECT id, name, email FROM users WHERE id = 1');
        self::assertSame($plan1->sql(), $plan2->sql());
        self::assertSame($plan1->kind(), $plan2->kind());
    }

    public function testReadPlanHasNoMutation(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT id, name, email FROM users WHERE id = 1');
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
    }

    public function testWritePlanHasNonNullMutation(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
    }

    public function testRewriteOutputIsNonEmpty(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite('SELECT id, name, email FROM users WHERE id = 1');
        self::assertNotEmpty($plan->sql());
        self::assertStringContainsString('SELECT', strtoupper($plan->sql()));
    }

    public function testInsertRewriteOutputContainsSelect(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);

        $plan = $rewriter->rewrite("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertMatchesRegularExpression('/^(?:WITH\b|SELECT\b)/i', $plan->sql(), 'INSERT rewrite must produce a result-select query starting with SELECT or WITH...SELECT');
    }
}
