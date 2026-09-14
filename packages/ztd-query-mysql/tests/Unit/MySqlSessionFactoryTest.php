<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\Connection\MySqlSessionSqlModeReflector;
use ZtdQuery\Platform\MySql\Connection\Result\MySqlResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\InsertSelectSourceExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\AlterTableMutation::class)]
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
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\LoadData\MySqlLoadDataProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Connection\Result\MySqlMysqliResultColumnTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\MySqlPartitioningParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Connection\Result\MySqlPdoResultColumnTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Transaction\MySqlTransactionStatementParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\MySqlUpsertExpressionParser::class)]
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
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Context\TableContext::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\StatementRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns::class)]
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
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor::class)]
#[CoversClass(MySqlSessionFactory::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlResultColumnTypeResolver::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(MySqlRewriter::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlSchemaReflector::class)]
#[UsesClass(MySqlSessionSqlModeReflector::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Diagnostic\MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\View\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\View\MySqlViewShadowRenderer::class)]
final class MySqlSessionFactoryTest extends TestCase
{
    public function testCreateRegistersReflectedViews(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $views = self::createStub(StatementInterface::class);
        $views->method('fetchAll')->willReturn([['name' => 'active_users']]);
        $create = self::createStub(StatementInterface::class);
        $create->method('fetchAll')->willReturn([
            ['Create View' => 'CREATE VIEW active_users AS SELECT 1 AS id'],
        ]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => match ($sql) {
                'SHOW TABLES' => $empty,
                "SHOW FULL TABLES WHERE Table_type = 'VIEW'" => $views,
                'SHOW CREATE VIEW `active_users`' => $create,
                default => $empty,
            },
        );

        $session = (new MySqlSessionFactory())->create($connection, ZtdConfig::default());

        self::assertSame(
            "WITH `active_users` AS (SELECT 1 AS id)\nSELECT * FROM active_users",
            $session->rewrite('SELECT * FROM active_users')->sql(),
        );
    }

    public function testCreateReturnsSession(): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([]);

        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $config = new ZtdConfig();
        $factory = new MySqlSessionFactory();
        $session = $factory->create($connection, $config);

        self::assertInstanceOf(MySqlResultColumnTypeResolver::class, $session->resultColumnTypeResolver());
    }

    public function testCreateAppliesReflectedAnsiQuotesModeToProductionLexing(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $sqlMode = self::createStub(StatementInterface::class);
        $sqlMode->method('fetchAll')->willReturn([['ztd_sql_mode' => 'STRICT_TRANS_TABLES,ANSI_QUOTES']]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => $sql === 'SELECT @@SESSION.sql_mode AS ztd_sql_mode'
                ? $sqlMode
                : $empty,
        );
        $previousMode = Context::getMode();

        try {
            (new MySqlSessionFactory())->create($connection, ZtdConfig::default());
            $tokens = SqlTokenStream::tokenize('"column"', MySqlLexerProfile::create())->significantTokens();

            self::assertSame(SqlTokenKind::QuotedIdentifier, $tokens[0]->kind);
        } finally {
            Context::setMode($previousMode);
        }
    }
}
