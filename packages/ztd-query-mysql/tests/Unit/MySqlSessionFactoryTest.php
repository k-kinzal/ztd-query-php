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
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\MySql\MySqlSessionSqlModeReflector;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[UsesClass(\ZtdQuery\Platform\MySql\DmlWhereClauseExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\InsertSelectSourceExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAction::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAlteration::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\CreateTableRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\OperationApplier::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\PrimaryKeyAlteration::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\UnsupportedKeyword::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\RowMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\SchemaMutation::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\TargetName::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLoadDataProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlMysqliResultColumnTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitioningParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlPdoResultColumnTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTransactionStatementParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\AssignmentExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\DiagnosticKeywords::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Transaction\TokenReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\ExpressionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\LiteralReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\StringLiteral::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\ColumnMapping::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\InputFile::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\InputFormat::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\InsertQuery::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\RecordParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\RowProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\MetadataColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Classification\CteStatementKind::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Context\TableContext::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\StatementRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\DefinitionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\TokenReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ReplaceStatementConverter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Enum\RankEdits::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\UpdateAssignmentExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\UpdateSourceExtractor::class)]
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
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewShadowRenderer::class)]
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
