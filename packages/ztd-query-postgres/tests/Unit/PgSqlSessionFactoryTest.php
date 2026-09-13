<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\Postgres\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlCopySupport;
use ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\PgSqlMutationResolver;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Platform\Postgres\PgSqlPartitionParser;
use ZtdQuery\Platform\Postgres\PgSqlPartitionReflector;
use ZtdQuery\Platform\Postgres\PgSqlPdoParameterBindingCompiler;
use ZtdQuery\Platform\Postgres\PgSqlPdoPlaceholderEscaper;
use ZtdQuery\Platform\Postgres\PgSqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;
use ZtdQuery\Platform\Postgres\PgSqlRewriter;
use ZtdQuery\Platform\Postgres\PgSqlSchemaParser;
use ZtdQuery\Platform\Postgres\PgSqlSchemaReflector;
use ZtdQuery\Platform\Postgres\PgSqlSessionFactory;
use ZtdQuery\Platform\Postgres\PgSqlTransformer;
use ZtdQuery\Platform\Postgres\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Postgres\Transformer\InsertTransformer;
use ZtdQuery\Platform\Postgres\Transformer\SelectTransformer;
use ZtdQuery\Platform\Postgres\Transformer\UpdateTransformer;

#[CoversClass(PgSqlSessionFactory::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlForeignKeyDefinitionParser::class)]
#[UsesClass(PgSqlParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PostgreSqlLexicalMasker::class)]
#[UsesClass(PgSqlSchemaParser::class)]
#[UsesClass(PgSqlPartitionParser::class)]
#[UsesClass(PgSqlPartitionReflector::class)]
#[UsesClass(PgSqlQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(PgSqlRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[UsesClass(PgSqlMutationResolver::class)]
#[UsesClass(PgSqlTransformer::class)]
#[UsesClass(PgSqlSchemaReflector::class)]
#[UsesClass(PgSqlCastRenderer::class)]
#[UsesClass(PgSqlCopySupport::class)]
#[UsesClass(PgSqlPdoParameterBindingCompiler::class)]
#[UsesClass(PgSqlPdoPlaceholderEscaper::class)]
#[UsesClass(PgSqlPdoResultColumnTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlValueRenderer::class)]
#[UsesClass(PgSqlIdentifierQuoter::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlTableSampleParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlTableSampleRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlViewShadowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionPredicateRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlReturningProjectionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[CoversClass(\ZtdQuery\Platform\Postgres\Session\SchemaInitializer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Copy\TargetSql::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Copy\TextFields::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\OperandInput::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\QuotedInput::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\TokenBoundary::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Mutation\DdlResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Mutation\DmlResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\PrefixMerge::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\ShadowDependencies::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Diagnostic\KeywordSearch::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ComparisonOperator::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionLexeme::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\PrecedenceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\PrimaryParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\RelationTarget::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Returning\ProjectionItem::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Sampling\SampleTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Classification::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\InsertSource::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\SelectColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\TableDefinitionClauses::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Transaction\KeywordForm::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlTableSample::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlTableSampleMethod::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlTransactionStatementParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\PartitionKeys::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Column\ColumnDefinitionSql::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Column\NativeTypeSql::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\ForeignKeyRow::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\ForeignKeys::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\IndexDefinitions::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\IndexRows::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\PrimaryColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\UniqueIndexes::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\Sampling\SampleProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\Sampling\TableColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnDefinition::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnTypeDeclaration::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableBody::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableConstraint::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableFields::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\StorageTable::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Session\SchemaContext::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Session\StatementRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\CastTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Value\BinaryStream::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Value\LiteralText::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\OrderedExpressions::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\SelectProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\UpsertProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\ValueProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\MergeTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Merge\MatchConditions::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Update\ColumnProjection::class)]
final class PgSqlSessionFactoryTest extends TestCase
{
    public function testCreateRegistersReflectedPartitionMetadata(): void
    {
        $tables = new FakeStatement([
            ['table_name' => 'logs'],
            ['table_name' => 'logs_2024'],
        ]);
        $columns = new FakeStatement([
            [
                'column_name' => 'id',
                'data_type' => 'integer',
                'character_maximum_length' => null,
                'numeric_precision' => 32,
                'numeric_scale' => 0,
                'is_nullable' => 'NO',
                'column_default' => null,
                'udt_name' => 'int4',
            ],
            [
                'column_name' => 'log_date',
                'data_type' => 'date',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'NO',
                'column_default' => null,
                'udt_name' => 'date',
            ],
        ]);
        $primaryKey = new FakeStatement([
            ['column_name' => 'id'],
            ['column_name' => 'log_date'],
        ]);
        $empty = new FakeStatement([]);
        $partitionKeys = new FakeStatement([
            ['table_name' => 'logs', 'partition_key' => 'RANGE (log_date)'],
        ]);
        $relations = new FakeStatement([
            [
                'child_table' => 'logs_2024',
                'parent_table' => 'logs',
                'predicate' => "log_date >= '2024-01-01'::date AND log_date < '2025-01-01'::date",
            ],
        ]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static function (string $sql) use (
                $tables,
                $columns,
                $primaryKey,
                $empty,
                $partitionKeys,
                $relations,
            ) {
                return match (true) {
                    str_contains($sql, 'information_schema.tables') => $tables,
                    str_contains($sql, 'information_schema.columns') => $columns,
                    str_contains($sql, "constraint_type = 'PRIMARY KEY'") => $primaryKey,
                    str_contains($sql, 'SELECT c.relname AS table_name') => $partitionKeys,
                    str_contains($sql, 'SELECT child.relname AS child_table') => $relations,
                    default => $empty,
                };
            },
        );

        $session = (new PgSqlSessionFactory())->create($connection, ZtdConfig::default());
        $sql = $session->rewrite('SELECT * FROM logs_2024')->sql();

        self::assertStringContainsString('"logs" AS MATERIALIZED', $sql);
        self::assertStringContainsString(
            '"logs_2024" AS MATERIALIZED (SELECT * FROM "logs" WHERE log_date >=',
            $sql,
        );
        $create = $session->rewrite(
            "CREATE TABLE logs_2025 PARTITION OF logs FOR VALUES FROM ('2025-01-01') TO ('2026-01-01')",
        );
        self::assertNotNull($create->mutation());
    }

    public function testCreateRegistersReflectedViews(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $views = self::createStub(StatementInterface::class);
        $views->method('fetchAll')->willReturn([
            ['viewname' => 'active_users', 'definition' => 'SELECT 1 AS id'],
        ]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => str_contains($sql, 'pg_views') ? $views : $empty,
        );

        $session = (new PgSqlSessionFactory())->create($connection, ZtdConfig::default());

        self::assertSame(
            "WITH \"active_users\" AS MATERIALIZED (SELECT 1 AS id)\nSELECT * FROM active_users",
            $session->rewrite('SELECT * FROM active_users')->sql(),
        );
    }

    public function testCreateReturnsSession(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturn($tablesStmt);

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());

        self::assertTrue($session->isEnabled());
        self::assertInstanceOf(PgSqlCopySupport::class, $session->copySupport());
        self::assertInstanceOf(PgSqlPdoParameterBindingCompiler::class, $session->parameterBindingCompiler());
        self::assertInstanceOf(PgSqlPdoResultColumnTypeResolver::class, $session->resultColumnTypeResolver());
    }

    public function testCreatedSessionIsEnabledByDefault(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturn($tablesStmt);

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());

        self::assertTrue($session->isEnabled());
    }

    public function testCreateWithTablesReflectsSchema(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([
            ['table_name' => 'users'],
        ]);

        $columnsStmt = static::createStub(StatementInterface::class);
        $columnsStmt->method('fetchAll')->willReturn([
            [
                'column_name' => 'id',
                'data_type' => 'INTEGER',
                'character_maximum_length' => null,
                'numeric_precision' => 32,
                'numeric_scale' => 0,
                'is_nullable' => 'NO',
                'column_default' => null,
                'udt_name' => 'int4',
            ],
            [
                'column_name' => 'name',
                'data_type' => 'TEXT',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'YES',
                'column_default' => null,
                'udt_name' => 'text',
            ],
        ]);

        $pkStmt = static::createStub(StatementInterface::class);
        $pkStmt->method('fetchAll')->willReturn([
            ['column_name' => 'id'],
        ]);

        $uniqueStmt = static::createStub(StatementInterface::class);
        $uniqueStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturnCallback(
            function (string $sql) use ($tablesStmt, $columnsStmt, $pkStmt, $uniqueStmt) {
                if (str_contains($sql, 'information_schema.tables')) {
                    return $tablesStmt;
                }
                if (str_contains($sql, 'information_schema.columns')) {
                    return $columnsStmt;
                }
                if (str_contains($sql, "constraint_type = 'PRIMARY KEY'")) {
                    return $pkStmt;
                }
                if (str_contains($sql, "constraint_type = 'UNIQUE'")) {
                    return $uniqueStmt;
                }

                return false;
            }
        );

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());

        self::assertTrue($session->isEnabled());
    }

    public function testCreateWithEmptyDatabaseReturnsSession(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $connection->method('query')->willReturn(false);

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());

        self::assertTrue($session->isEnabled());
    }

    public function testSessionCanBeEnabledAfterCreation(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturn($tablesStmt);

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());

        $session->enable();
        self::assertTrue($session->isEnabled());
    }

    public function testCreateWithTableRegistersSchemaInSession(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([
            ['table_name' => 'products'],
        ]);

        $columnsStmt = static::createStub(StatementInterface::class);
        $columnsStmt->method('fetchAll')->willReturn([
            [
                'column_name' => 'id',
                'data_type' => 'INTEGER',
                'character_maximum_length' => null,
                'numeric_precision' => 32,
                'numeric_scale' => 0,
                'is_nullable' => 'NO',
                'column_default' => null,
                'udt_name' => 'int4',
            ],
            [
                'column_name' => 'price',
                'data_type' => 'NUMERIC',
                'character_maximum_length' => null,
                'numeric_precision' => 10,
                'numeric_scale' => 2,
                'is_nullable' => 'YES',
                'column_default' => null,
                'udt_name' => 'numeric',
            ],
        ]);

        $pkStmt = static::createStub(StatementInterface::class);
        $pkStmt->method('fetchAll')->willReturn([
            ['column_name' => 'id'],
        ]);

        $uniqueStmt = static::createStub(StatementInterface::class);
        $uniqueStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturnCallback(
            function (string $sql) use ($tablesStmt, $columnsStmt, $pkStmt, $uniqueStmt) {
                if (str_contains($sql, 'information_schema.tables')) {
                    return $tablesStmt;
                }
                if (str_contains($sql, 'information_schema.columns')) {
                    return $columnsStmt;
                }
                if (str_contains($sql, "constraint_type = 'PRIMARY KEY'")) {
                    return $pkStmt;
                }
                if (str_contains($sql, "constraint_type = 'UNIQUE'")) {
                    return $uniqueStmt;
                }

                return false;
            }
        );

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());
        $session->enable();

        $plan = $session->rewrite('SELECT * FROM products');
        self::assertStringContainsString('"products" AS MATERIALIZED', $plan->sql());
    }

    public function testCreateWithNullParseResultStillWorks(): void
    {
        $connection = static::createStub(ConnectionInterface::class);

        $tablesStmt = static::createStub(StatementInterface::class);
        $tablesStmt->method('fetchAll')->willReturn([
            ['table_name' => 'bad_table'],
        ]);

        $columnsStmt = static::createStub(StatementInterface::class);
        $columnsStmt->method('fetchAll')->willReturn([]);

        $connection->method('query')->willReturnCallback(
            function (string $sql) use ($tablesStmt, $columnsStmt) {
                if (str_contains($sql, 'information_schema.tables')) {
                    return $tablesStmt;
                }

                return $columnsStmt;
            }
        );

        $factory = new PgSqlSessionFactory();
        $session = $factory->create($connection, ZtdConfig::default());
        self::assertTrue($session->isEnabled());
    }

    public function testCreateRegistersReflectedPartialUniqueIndexes(): void
    {
        $tables = new FakeStatement([
            ['table_name' => 'users'],
        ]);
        $columns = new FakeStatement([
            ['column_name' => 'id', 'data_type' => 'integer', 'is_nullable' => 'NO', 'udt_name' => 'int4'],
            ['column_name' => 'email', 'data_type' => 'text', 'is_nullable' => 'NO', 'udt_name' => 'text'],
            ['column_name' => 'status', 'data_type' => 'text', 'is_nullable' => 'NO', 'udt_name' => 'text'],
        ]);
        $primaryKey = new FakeStatement([
            ['column_name' => 'id'],
        ]);
        $unique = new FakeStatement([
            ['constraint_name' => 'users_active_email', 'column_name' => 'email', 'predicate' => "status = 'active'"],
        ]);
        $empty = new FakeStatement([]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static function (string $sql) use ($tables, $columns, $primaryKey, $unique, $empty): StatementInterface {
                return match (true) {
                    str_contains($sql, 'information_schema.tables') => $tables,
                    str_contains($sql, 'information_schema.columns') => $columns,
                    str_contains($sql, "constraint_type = 'PRIMARY KEY'") => $primaryKey,
                    str_contains($sql, 'pg_catalog.pg_index') => $unique,
                    default => $empty,
                };
            },
        );

        $session = (new PgSqlSessionFactory())->create($connection, ZtdConfig::default());
        $plan = $session->rewrite(
            "INSERT INTO users (id, email, status) VALUES (1, 'a@example.com', 'active') "
                . "ON CONFLICT (email) WHERE status = 'active' "
                . 'DO UPDATE SET status = EXCLUDED.status',
        );

        self::assertStringContainsString('"__ztd_existing"."email" = "__ztd_incoming"."email"', $plan->sql());
        self::assertStringContainsString('"__ztd_existing"."status" = \'active\'', $plan->sql());
    }
}
