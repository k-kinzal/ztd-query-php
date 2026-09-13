<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteInMemoryAttachStatement;
use ZtdQuery\Platform\Sqlite\SqliteLexicalMasker;
use ZtdQuery\Platform\Sqlite\SqliteMutationResolver;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqlitePdoParameterBindingCompiler;
use ZtdQuery\Platform\Sqlite\SqlitePdoResultColumnTypeResolver;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Platform\Sqlite\SqliteRewriter;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Platform\Sqlite\SqliteSchemaReflector;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;
use ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer;

#[CoversClass(SqliteSessionFactory::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Binding\ParameterReplacements::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation::class)]
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
#[UsesClass(SqliteInMemoryAttachStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(SqliteLexicalMasker::class)]
#[UsesClass(SqliteMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteNativeUpsertProjector::class)]
#[UsesClass(SqliteParser::class)]
#[UsesClass(SqlitePdoParameterBindingCompiler::class)]
#[UsesClass(SqlitePdoResultColumnTypeResolver::class)]
#[UsesClass(SqliteQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser::class)]
#[UsesClass(SqliteRewriter::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteSchemaReflector::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteTransactionStatementParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteViewDefinitionParser::class)]
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
final class SqliteSessionFactoryTest extends TestCase
{
    public function testCreateRegistersReflectedViews(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $views = self::createStub(StatementInterface::class);
        $views->method('fetchAll')->willReturn([
            ['name' => 'active_users', 'sql' => 'CREATE VIEW active_users AS SELECT 1 AS id'],
        ]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => str_contains($sql, "type='view'") ? $views : $empty,
        );

        $session = (new SqliteSessionFactory())->create($connection, ZtdConfig::default());

        self::assertSame(
            "WITH \"active_users\" AS (SELECT 1 AS id)\nSELECT * FROM active_users",
            $session->rewrite('SELECT * FROM active_users')->sql(),
        );
    }

    public function testCreateReturnsSession(): void
    {
        $statement = static::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([]);

        $connection = static::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $config = new ZtdConfig();
        $factory = new SqliteSessionFactory();
        $session = $factory->create($connection, $config);

        self::assertTrue($session->isEnabled());
        self::assertInstanceOf(SqlitePdoParameterBindingCompiler::class, $session->parameterBindingCompiler());
        self::assertInstanceOf(SqlitePdoResultColumnTypeResolver::class, $session->resultColumnTypeResolver());
    }

    public function testCreateWithExistingTablesRegistersDefinitions(): void
    {
        $statement = static::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([
            ['name' => 'users', 'sql' => 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'],
        ]);

        $connection = static::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $config = new ZtdConfig();
        $factory = new SqliteSessionFactory();
        $session = $factory->create($connection, $config);

        self::assertTrue($session->isEnabled());

        $plan = $session->rewrite('SELECT * FROM users');
        self::assertStringContainsString('WITH', $plan->sql());
    }

    public function testCreateWithUnparseableSchemaSkipsTable(): void
    {
        $statement = static::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([
            ['name' => 'bad', 'sql' => 'not valid sql'],
            ['name' => 'good', 'sql' => 'CREATE TABLE good (id INTEGER PRIMARY KEY)'],
        ]);

        $connection = static::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $config = new ZtdConfig();
        $factory = new SqliteSessionFactory();
        $session = $factory->create($connection, $config);

        self::assertTrue($session->isEnabled());
    }
}
