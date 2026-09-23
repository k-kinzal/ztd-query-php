<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class TypeReaderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testReadPreservesDecimalPrecisionAndScale(Dialect $dialect): void
    {
        $table = (new SchemaBuilder($dialect))->build('CREATE TABLE users (amount DECIMAL(10, 2))')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericStorage::class, $table->columns[0]->type->identity);
        self::assertNotNull($table->columns[0]->type->identity->precision);
        self::assertNotNull($table->columns[0]->type->identity->scale);
        self::assertSame('10', $table->columns[0]->type->identity->precision->spelling);
        self::assertSame('2', $table->columns[0]->type->identity->scale->spelling);
    }

    public function testAffinityPreservesSqliteDeclaredTypes(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame('text', $table->columns[0]->type->affinity?->value);
    }

    public function testCanonicalRejectsUnsupportedDomainNames(): void
    {
        $reader = new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql);
        self::assertNull($reader->canonical('CUSTOM_DOMAIN'));
        self::assertSame('integer', $reader->canonical('INT4'));
        self::assertSame('boolean', $reader->canonical('BOOL'));
    }

    #[DataProvider('providerCanonicalTypes')]
    public function testCanonicalModelsOnlyKnownDialectTypes(Dialect $dialect, string $input, ?string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Ast\TypeReader($dialect))->canonical($input));
    }

    /**
     * @return iterable<string, array{Dialect, string, ?string}>
     */
    public static function providerCanonicalTypes(): iterable
    {
        yield 'PostgreSql-INT' => [Dialect::PostgreSql, 'INT', 'integer'];
        yield 'PostgreSql-INT4' => [Dialect::PostgreSql, 'INT4', 'integer'];
        yield 'PostgreSql-SMALLINT' => [Dialect::PostgreSql, 'SMALLINT', 'smallint'];
        yield 'PostgreSql-INT2' => [Dialect::PostgreSql, 'INT2', 'smallint'];
        yield 'PostgreSql-BIGINT' => [Dialect::PostgreSql, 'BIGINT', 'bigint'];
        yield 'PostgreSql-INT8' => [Dialect::PostgreSql, 'INT8', 'bigint'];
        yield 'PostgreSql-DEC' => [Dialect::PostgreSql, 'DEC', 'numeric'];
        yield 'PostgreSql-DECIMAL' => [Dialect::PostgreSql, 'DECIMAL', 'numeric'];
        yield 'PostgreSql-NUMERIC' => [Dialect::PostgreSql, 'NUMERIC', 'numeric'];
        yield 'PostgreSql-REAL' => [Dialect::PostgreSql, 'REAL', 'real'];
        yield 'PostgreSql-FLOAT4' => [Dialect::PostgreSql, 'FLOAT4', 'real'];
        yield 'PostgreSql-DOUBLE' => [Dialect::PostgreSql, 'DOUBLE', 'double precision'];
        yield 'PostgreSql-DOUBLE PRECISION' => [Dialect::PostgreSql, 'DOUBLE PRECISION', 'double precision'];
        yield 'PostgreSql-FLOAT8' => [Dialect::PostgreSql, 'FLOAT8', 'double precision'];
        yield 'PostgreSql-BOOL' => [Dialect::PostgreSql, 'BOOL', 'boolean'];
        yield 'PostgreSql-BOOLEAN' => [Dialect::PostgreSql, 'BOOLEAN', 'boolean'];
        yield 'PostgreSql-VARCHAR' => [Dialect::PostgreSql, 'VARCHAR', 'varchar'];
        yield 'PostgreSql-CHARACTER VARYING' => [Dialect::PostgreSql, 'CHARACTER VARYING', 'varchar'];
        yield 'PostgreSql-CHAR VARYING' => [Dialect::PostgreSql, 'CHAR VARYING', 'varchar'];
        yield 'PostgreSql-CHAR' => [Dialect::PostgreSql, 'CHAR', 'char'];
        yield 'PostgreSql-CHARACTER' => [Dialect::PostgreSql, 'CHARACTER', 'char'];
        yield 'PostgreSql-TEXT' => [Dialect::PostgreSql, 'TEXT', 'text'];
        yield 'PostgreSql-DATE' => [Dialect::PostgreSql, 'DATE', 'date'];
        yield 'PostgreSql-TIME' => [Dialect::PostgreSql, 'TIME', 'time'];
        yield 'PostgreSql-TIMESTAMP' => [Dialect::PostgreSql, 'TIMESTAMP', 'timestamp'];
        yield 'PostgreSql-JSON' => [Dialect::PostgreSql, 'JSON', 'json'];
        yield 'PostgreSql-TINYINT' => [Dialect::PostgreSql, 'TINYINT', null];
        yield 'PostgreSql-MEDIUMINT' => [Dialect::PostgreSql, 'MEDIUMINT', null];
        yield 'PostgreSql-DATETIME' => [Dialect::PostgreSql, 'DATETIME', null];
        yield 'PostgreSql-BLOB' => [Dialect::PostgreSql, 'BLOB', null];
        yield 'PostgreSql-UUID' => [Dialect::PostgreSql, 'UUID', 'uuid'];
        yield 'PostgreSql-BYTEA' => [Dialect::PostgreSql, 'BYTEA', 'bytea'];
        yield 'PostgreSql-JSONB' => [Dialect::PostgreSql, 'JSONB', 'jsonb'];
        yield 'PostgreSql-TIMESTAMPTZ' => [Dialect::PostgreSql, 'TIMESTAMPTZ', 'timestamptz'];
        yield 'PostgreSql-TIMETZ' => [Dialect::PostgreSql, 'TIMETZ', 'timetz'];
        yield 'PostgreSql-INTERVAL' => [Dialect::PostgreSql, 'INTERVAL', 'interval'];
        yield 'PostgreSql-UNSUPPORTED' => [Dialect::PostgreSql, 'UNSUPPORTED', null];
        yield 'MySql-INT' => [Dialect::MySql, 'INT', 'integer'];
        yield 'MySql-INT4' => [Dialect::MySql, 'INT4', 'integer'];
        yield 'MySql-SMALLINT' => [Dialect::MySql, 'SMALLINT', 'smallint'];
        yield 'MySql-INT2' => [Dialect::MySql, 'INT2', 'smallint'];
        yield 'MySql-BIGINT' => [Dialect::MySql, 'BIGINT', 'bigint'];
        yield 'MySql-INT8' => [Dialect::MySql, 'INT8', 'bigint'];
        yield 'MySql-DEC' => [Dialect::MySql, 'DEC', 'numeric'];
        yield 'MySql-DECIMAL' => [Dialect::MySql, 'DECIMAL', 'numeric'];
        yield 'MySql-NUMERIC' => [Dialect::MySql, 'NUMERIC', 'numeric'];
        yield 'MySql-REAL' => [Dialect::MySql, 'REAL', 'double precision'];
        yield 'MySql-FLOAT4' => [Dialect::MySql, 'FLOAT4', 'real'];
        yield 'MySql-DOUBLE' => [Dialect::MySql, 'DOUBLE', 'double precision'];
        yield 'MySql-DOUBLE PRECISION' => [Dialect::MySql, 'DOUBLE PRECISION', 'double precision'];
        yield 'MySql-FLOAT8' => [Dialect::MySql, 'FLOAT8', 'double precision'];
        yield 'MySql-BOOL' => [Dialect::MySql, 'BOOL', 'tinyint'];
        yield 'MySql-BOOLEAN' => [Dialect::MySql, 'BOOLEAN', 'tinyint'];
        yield 'MySql-VARCHAR' => [Dialect::MySql, 'VARCHAR', 'varchar'];
        yield 'MySql-CHARACTER VARYING' => [Dialect::MySql, 'CHARACTER VARYING', 'varchar'];
        yield 'MySql-CHAR VARYING' => [Dialect::MySql, 'CHAR VARYING', 'varchar'];
        yield 'MySql-CHAR' => [Dialect::MySql, 'CHAR', 'char'];
        yield 'MySql-CHARACTER' => [Dialect::MySql, 'CHARACTER', 'char'];
        yield 'MySql-TEXT' => [Dialect::MySql, 'TEXT', 'text'];
        yield 'MySql-DATE' => [Dialect::MySql, 'DATE', 'date'];
        yield 'MySql-TIME' => [Dialect::MySql, 'TIME', 'time'];
        yield 'MySql-TIMESTAMP' => [Dialect::MySql, 'TIMESTAMP', 'timestamp'];
        yield 'MySql-JSON' => [Dialect::MySql, 'JSON', 'json'];
        yield 'MySql-TINYINT' => [Dialect::MySql, 'TINYINT', 'tinyint'];
        yield 'MySql-MEDIUMINT' => [Dialect::MySql, 'MEDIUMINT', 'mediumint'];
        yield 'MySql-DATETIME' => [Dialect::MySql, 'DATETIME', 'datetime'];
        yield 'MySql-BLOB' => [Dialect::MySql, 'BLOB', 'blob'];
        yield 'MySql-UUID' => [Dialect::MySql, 'UUID', null];
        yield 'MySql-BYTEA' => [Dialect::MySql, 'BYTEA', null];
        yield 'MySql-JSONB' => [Dialect::MySql, 'JSONB', null];
        yield 'MySql-TIMESTAMPTZ' => [Dialect::MySql, 'TIMESTAMPTZ', null];
        yield 'MySql-TIMETZ' => [Dialect::MySql, 'TIMETZ', null];
        yield 'MySql-INTERVAL' => [Dialect::MySql, 'INTERVAL', null];
        yield 'MySql-UNSUPPORTED' => [Dialect::MySql, 'UNSUPPORTED', null];
    }

    #[DataProvider('providerAffinities')]
    public function testAffinityUsesSqlitePrecedence(string $input, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Ast\TypeReader(Dialect::Sqlite))->affinity($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerAffinities(): iterable
    {
        yield 'FLOATING POINT' => ['FLOATING POINT', 'integer'];
        yield 'CHARINT' => ['CHARINT', 'integer'];
        yield 'VARCHAR' => ['VARCHAR', 'text'];
        yield 'CLOB' => ['CLOB', 'text'];
        yield 'TEXT' => ['TEXT', 'text'];
        yield 'empty' => ['', 'blob'];
        yield 'BLOB' => ['BLOB', 'blob'];
        yield 'REAL' => ['REAL', 'real'];
        yield 'FLOAT' => ['FLOAT', 'real'];
        yield 'DOUBLE' => ['DOUBLE', 'real'];
        yield 'BOOLEAN' => ['BOOLEAN', 'numeric'];
    }
    public function testReadPreservesSqliteDeclaredNumericParameters(): void
    {
        $type = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(amount DECIMAL(10,2))')->tables[0]->columns[0]->type;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\SqliteDeclaration::class, $type->identity);
        self::assertNotNull($type->identity->size);
        self::assertNotNull($type->identity->scale);
        self::assertSame('10', $type->identity->size->spelling);
        self::assertSame('2', $type->identity->scale->spelling);
    }

}
