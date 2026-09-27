<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\Nullability;
use Tests\Contract\Resolved;

#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
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
        $table = Resolved::of((new Semantics($dialect))->analyze('CREATE TABLE users (amount DECIMAL(10, 2))', []))->declarations[0];
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::Numeric, $table->columns[0]->type->name);
        self::assertSame(10, $table->columns[0]->type->precision);
        self::assertSame(2, $table->columns[0]->type->scale);
        self::assertNull($table->columns[0]->type->length);
    }

    public function testReadKeepsSqliteDeclaredTypesWithTheirAffinity(): void
    {
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)', []))->declarations[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame(\SqlSemantics\Statement\Declaration\Affinity::Text, $table->columns[0]->type->affinity);
        self::assertNull(Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE users (id TEXT)', []))->declarations[0]->columns[0]->type->affinity);
    }

    public function testReadKeepsUnmodeledNamesAsTypeNames(): void
    {
        $column = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE users (amount app.custom_domain(3))', []))->declarations[0]->columns[0];
        self::assertInstanceOf(\SqlSemantics\Statement\Declaration\TypeName::class, $column->type->name);
        self::assertSame(['app', 'custom_domain'], $column->type->name->parts);
        self::assertNull($column->type->length);
    }

    #[DataProvider('providerSynonyms')]
    public function testReadResolvesEverySynonymToTheSameBuiltinType(Dialect $dialect, string $declaration, \SqlSemantics\Statement\Declaration\Builtin $expected): void
    {
        $column = Resolved::of((new Semantics($dialect))->analyze('CREATE TABLE users (value ' . $declaration . ')', []))->declarations[0]->columns[0];
        self::assertSame($expected, $column->type->name);
    }

    /**
     * @return iterable<string, array{Dialect, string, \SqlSemantics\Statement\Declaration\Builtin}>
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
            yield $dialect->value . '-' . $declaration => [$dialect, $declaration, constant(\SqlSemantics\Statement\Declaration\Builtin::class . '::' . $case)];
        }
    }
}
