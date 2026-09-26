<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Core\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Core\Type\Builtin::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeName::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Model\Operator::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class TypeReaderTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testReadPreservesDecimalPrecisionAndScale(Dialect $dialect): void
    {
        $table = (new SchemaBuilder($dialect))->build('CREATE TABLE users (amount DECIMAL(10, 2))')->tables[0];
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Numeric, $table->columns[0]->type->name);
        self::assertSame(10, $table->columns[0]->type->precision);
        self::assertSame(2, $table->columns[0]->type->scale);
        self::assertNull($table->columns[0]->type->length);
    }

    public function testReadKeepsSqliteDeclaredTypesWithTheirAffinity(): void
    {
        $table = (new SchemaBuilder(SqliteDialect::Sqlite))->build('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame(\SqlSemantics\Core\Type\Affinity::Text, $table->columns[0]->type->affinity);
        self::assertNull((new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (id TEXT)')->tables[0]->columns[0]->type->affinity);
    }

    public function testReadKeepsUnmodeledNamesAsTypeNames(): void
    {
        $column = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (amount app.custom_domain(3))')->tables[0]->columns[0];
        self::assertInstanceOf(\SqlSemantics\Core\Type\TypeName::class, $column->type->name);
        self::assertSame(['app', 'custom_domain'], $column->type->name->parts);
        self::assertNull($column->type->length);
    }

    #[DataProvider('providerSynonyms')]
    public function testReadResolvesEverySynonymToTheSameBuiltinType(Dialect $dialect, string $declaration, \SqlSemantics\Core\Type\Builtin $expected): void
    {
        $column = (new SchemaBuilder($dialect))->build('CREATE TABLE users (value ' . $declaration . ')')->tables[0]->columns[0];
        self::assertSame($expected, $column->type->name);
    }

    /**
     * @return iterable<string, array{Dialect, string, \SqlSemantics\Core\Type\Builtin}>
     */
    public static function providerSynonyms(): iterable
    {
        $rows = [
            [PostgreSqlDialect::PostgreSql, 'INT', 'Integer'], [PostgreSqlDialect::PostgreSql, 'INTEGER', 'Integer'], [PostgreSqlDialect::PostgreSql, 'INT4', 'Integer'],
            [PostgreSqlDialect::PostgreSql, 'SMALLINT', 'SmallInt'], [PostgreSqlDialect::PostgreSql, 'INT2', 'SmallInt'],
            [PostgreSqlDialect::PostgreSql, 'BIGINT', 'BigInt'], [PostgreSqlDialect::PostgreSql, 'INT8', 'BigInt'],
            [PostgreSqlDialect::PostgreSql, 'DEC', 'Numeric'], [PostgreSqlDialect::PostgreSql, 'DECIMAL', 'Numeric'], [PostgreSqlDialect::PostgreSql, 'NUMERIC', 'Numeric'],
            [PostgreSqlDialect::PostgreSql, 'REAL', 'Real'], [PostgreSqlDialect::PostgreSql, 'FLOAT4', 'Real'],
            [PostgreSqlDialect::PostgreSql, 'DOUBLE PRECISION', 'DoublePrecision'], [PostgreSqlDialect::PostgreSql, 'FLOAT8', 'DoublePrecision'], [PostgreSqlDialect::PostgreSql, 'FLOAT', 'DoublePrecision'],
            [PostgreSqlDialect::PostgreSql, 'BOOL', 'Boolean'], [PostgreSqlDialect::PostgreSql, 'BOOLEAN', 'Boolean'],
            [PostgreSqlDialect::PostgreSql, 'VARCHAR', 'VarChar'], [PostgreSqlDialect::PostgreSql, 'CHARACTER VARYING', 'VarChar'], [PostgreSqlDialect::PostgreSql, 'CHAR VARYING', 'VarChar'],
            [PostgreSqlDialect::PostgreSql, 'CHAR', 'Char'], [PostgreSqlDialect::PostgreSql, 'CHARACTER', 'Char'], [PostgreSqlDialect::PostgreSql, 'BPCHAR', 'Char'],
            [PostgreSqlDialect::PostgreSql, 'TEXT', 'Text'], [PostgreSqlDialect::PostgreSql, 'DATE', 'Date'], [PostgreSqlDialect::PostgreSql, 'TIME', 'Time'],
            [PostgreSqlDialect::PostgreSql, 'TIMESTAMP', 'Timestamp'], [PostgreSqlDialect::PostgreSql, 'TIMESTAMPTZ', 'TimestampTz'], [PostgreSqlDialect::PostgreSql, 'TIMETZ', 'TimeTz'],
            [PostgreSqlDialect::PostgreSql, 'JSON', 'Json'], [PostgreSqlDialect::PostgreSql, 'JSONB', 'Jsonb'], [PostgreSqlDialect::PostgreSql, 'UUID', 'Uuid'],
            [PostgreSqlDialect::PostgreSql, 'BYTEA', 'Bytea'], [PostgreSqlDialect::PostgreSql, 'INTERVAL', 'Interval'],
            [MySqlDialect::MySql, 'INT', 'Integer'], [MySqlDialect::MySql, 'INTEGER', 'Integer'], [MySqlDialect::MySql, 'INT4', 'Integer'],
            [MySqlDialect::MySql, 'TINYINT', 'TinyInt'], [MySqlDialect::MySql, 'INT1', 'TinyInt'], [MySqlDialect::MySql, 'BOOL', 'TinyInt'], [MySqlDialect::MySql, 'BOOLEAN', 'TinyInt'],
            [MySqlDialect::MySql, 'SMALLINT', 'SmallInt'], [MySqlDialect::MySql, 'INT2', 'SmallInt'],
            [MySqlDialect::MySql, 'MEDIUMINT', 'MediumInt'], [MySqlDialect::MySql, 'INT3', 'MediumInt'], [MySqlDialect::MySql, 'MIDDLEINT', 'MediumInt'],
            [MySqlDialect::MySql, 'BIGINT', 'BigInt'], [MySqlDialect::MySql, 'INT8', 'BigInt'],
            [MySqlDialect::MySql, 'DEC', 'Numeric'], [MySqlDialect::MySql, 'DECIMAL', 'Numeric'], [MySqlDialect::MySql, 'NUMERIC', 'Numeric'], [MySqlDialect::MySql, 'FIXED', 'Numeric'],
            [MySqlDialect::MySql, 'FLOAT', 'Real'], [MySqlDialect::MySql, 'FLOAT4', 'Real'],
            [MySqlDialect::MySql, 'DOUBLE', 'DoublePrecision'], [MySqlDialect::MySql, 'DOUBLE PRECISION', 'DoublePrecision'], [MySqlDialect::MySql, 'FLOAT8', 'DoublePrecision'], [MySqlDialect::MySql, 'REAL', 'DoublePrecision'],
            [MySqlDialect::MySql, 'VARCHAR(1)', 'VarChar'], [MySqlDialect::MySql, 'CHARACTER VARYING(1)', 'VarChar'], [MySqlDialect::MySql, 'CHAR VARYING(1)', 'VarChar'], [MySqlDialect::MySql, 'NVARCHAR(1)', 'VarChar'], [MySqlDialect::MySql, 'NATIONAL VARCHAR(1)', 'VarChar'],
            [MySqlDialect::MySql, 'CHAR', 'Char'], [MySqlDialect::MySql, 'CHARACTER', 'Char'], [MySqlDialect::MySql, 'NCHAR', 'Char'], [MySqlDialect::MySql, 'NATIONAL CHAR', 'Char'],
            [MySqlDialect::MySql, 'TEXT', 'Text'], [MySqlDialect::MySql, 'LONG', 'MediumText'], [MySqlDialect::MySql, 'LONG VARCHAR', 'MediumText'], [MySqlDialect::MySql, 'LONG VARBINARY', 'MediumBlob'],
            [MySqlDialect::MySql, 'DATE', 'Date'], [MySqlDialect::MySql, 'TIME', 'Time'], [MySqlDialect::MySql, 'TIMESTAMP', 'Timestamp'], [MySqlDialect::MySql, 'DATETIME', 'DateTime'], [MySqlDialect::MySql, 'JSON', 'Json'], [MySqlDialect::MySql, 'BLOB', 'Blob'],
            [MySqlDialect::MySql, 'CHAR BYTE', 'Binary'], [MySqlDialect::MySql, 'CHAR CHARACTER SET binary', 'Binary'], [MySqlDialect::MySql, 'VARCHAR(1) CHARSET binary', 'VarBinary'], [MySqlDialect::MySql, 'TEXT CHARSET binary', 'Blob'],
            [SqliteDialect::Sqlite, 'INT', 'Integer'], [SqliteDialect::Sqlite, 'INTEGER', 'Integer'], [SqliteDialect::Sqlite, 'INT2', 'SmallInt'], [SqliteDialect::Sqlite, 'INT8', 'BigInt'],
            [SqliteDialect::Sqlite, 'VARYING CHARACTER', 'VarChar'], [SqliteDialect::Sqlite, 'CLOB', 'Text'], [SqliteDialect::Sqlite, 'DOUBLE PRECISION', 'DoublePrecision'], [SqliteDialect::Sqlite, 'BOOLEAN', 'Boolean'], [SqliteDialect::Sqlite, 'ANY', 'Any'],
        ];
        foreach ($rows as [$dialect, $declaration, $case]) {
            yield $dialect->value . '-' . $declaration => [$dialect, $declaration, constant(\SqlSemantics\Core\Type\Builtin::class . '::' . $case)];
        }
    }
}
