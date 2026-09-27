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
        self::assertSame(['10', '2'], $table->columns[0]->type->modifiers);
    }

    public function testAffinityPreservesSqliteDeclaredTypes(): void
    {
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)', []))->declarations[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame('text', $table->columns[0]->type->affinity);
    }

    public function testCanonicalRejectsUnsupportedDomainNames(): void
    {
        $reader = new \SqlSemantics\Core\Ast\TypeReader(PostgreSqlDialect::PostgreSql);
        self::assertNull($reader->canonical('CUSTOM_DOMAIN'));
        self::assertSame('integer', $reader->canonical('INT4'));
        self::assertSame('boolean', $reader->canonical('BOOL'));
    }

    #[DataProvider('providerCanonicalTypes')]
    public function testCanonicalModelsOnlyKnownDialectTypes(Dialect $dialect, string $input, ?string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Core\Ast\TypeReader($dialect))->canonical($input));
    }

    /**
     * @return iterable<string, array{Dialect, string, ?string}>
     */
    public static function providerCanonicalTypes(): iterable
    {
        yield 'PostgreSql-INT' => [PostgreSqlDialect::PostgreSql, 'INT', 'integer'];
        yield 'PostgreSql-INT4' => [PostgreSqlDialect::PostgreSql, 'INT4', 'integer'];
        yield 'PostgreSql-SMALLINT' => [PostgreSqlDialect::PostgreSql, 'SMALLINT', 'smallint'];
        yield 'PostgreSql-INT2' => [PostgreSqlDialect::PostgreSql, 'INT2', 'smallint'];
        yield 'PostgreSql-BIGINT' => [PostgreSqlDialect::PostgreSql, 'BIGINT', 'bigint'];
        yield 'PostgreSql-INT8' => [PostgreSqlDialect::PostgreSql, 'INT8', 'bigint'];
        yield 'PostgreSql-DEC' => [PostgreSqlDialect::PostgreSql, 'DEC', 'numeric'];
        yield 'PostgreSql-DECIMAL' => [PostgreSqlDialect::PostgreSql, 'DECIMAL', 'numeric'];
        yield 'PostgreSql-NUMERIC' => [PostgreSqlDialect::PostgreSql, 'NUMERIC', 'numeric'];
        yield 'PostgreSql-REAL' => [PostgreSqlDialect::PostgreSql, 'REAL', 'real'];
        yield 'PostgreSql-FLOAT4' => [PostgreSqlDialect::PostgreSql, 'FLOAT4', 'real'];
        yield 'PostgreSql-DOUBLE' => [PostgreSqlDialect::PostgreSql, 'DOUBLE', 'double precision'];
        yield 'PostgreSql-DOUBLE PRECISION' => [PostgreSqlDialect::PostgreSql, 'DOUBLE PRECISION', 'double precision'];
        yield 'PostgreSql-FLOAT8' => [PostgreSqlDialect::PostgreSql, 'FLOAT8', 'double precision'];
        yield 'PostgreSql-BOOL' => [PostgreSqlDialect::PostgreSql, 'BOOL', 'boolean'];
        yield 'PostgreSql-BOOLEAN' => [PostgreSqlDialect::PostgreSql, 'BOOLEAN', 'boolean'];
        yield 'PostgreSql-VARCHAR' => [PostgreSqlDialect::PostgreSql, 'VARCHAR', 'varchar'];
        yield 'PostgreSql-CHARACTER VARYING' => [PostgreSqlDialect::PostgreSql, 'CHARACTER VARYING', 'varchar'];
        yield 'PostgreSql-CHAR VARYING' => [PostgreSqlDialect::PostgreSql, 'CHAR VARYING', 'varchar'];
        yield 'PostgreSql-CHAR' => [PostgreSqlDialect::PostgreSql, 'CHAR', 'char'];
        yield 'PostgreSql-CHARACTER' => [PostgreSqlDialect::PostgreSql, 'CHARACTER', 'char'];
        yield 'PostgreSql-TEXT' => [PostgreSqlDialect::PostgreSql, 'TEXT', 'text'];
        yield 'PostgreSql-DATE' => [PostgreSqlDialect::PostgreSql, 'DATE', 'date'];
        yield 'PostgreSql-TIME' => [PostgreSqlDialect::PostgreSql, 'TIME', 'time'];
        yield 'PostgreSql-TIMESTAMP' => [PostgreSqlDialect::PostgreSql, 'TIMESTAMP', 'timestamp'];
        yield 'PostgreSql-JSON' => [PostgreSqlDialect::PostgreSql, 'JSON', 'json'];
        yield 'PostgreSql-TINYINT' => [PostgreSqlDialect::PostgreSql, 'TINYINT', null];
        yield 'PostgreSql-MEDIUMINT' => [PostgreSqlDialect::PostgreSql, 'MEDIUMINT', null];
        yield 'PostgreSql-DATETIME' => [PostgreSqlDialect::PostgreSql, 'DATETIME', null];
        yield 'PostgreSql-BLOB' => [PostgreSqlDialect::PostgreSql, 'BLOB', null];
        yield 'PostgreSql-UUID' => [PostgreSqlDialect::PostgreSql, 'UUID', 'uuid'];
        yield 'PostgreSql-BYTEA' => [PostgreSqlDialect::PostgreSql, 'BYTEA', 'bytea'];
        yield 'PostgreSql-JSONB' => [PostgreSqlDialect::PostgreSql, 'JSONB', 'jsonb'];
        yield 'PostgreSql-TIMESTAMPTZ' => [PostgreSqlDialect::PostgreSql, 'TIMESTAMPTZ', 'timestamptz'];
        yield 'PostgreSql-TIMETZ' => [PostgreSqlDialect::PostgreSql, 'TIMETZ', 'timetz'];
        yield 'PostgreSql-INTERVAL' => [PostgreSqlDialect::PostgreSql, 'INTERVAL', 'interval'];
        yield 'PostgreSql-UNSUPPORTED' => [PostgreSqlDialect::PostgreSql, 'UNSUPPORTED', null];
        yield 'MySql-INT' => [MySqlDialect::MySql, 'INT', 'integer'];
        yield 'MySql-INT4' => [MySqlDialect::MySql, 'INT4', 'integer'];
        yield 'MySql-SMALLINT' => [MySqlDialect::MySql, 'SMALLINT', 'smallint'];
        yield 'MySql-INT2' => [MySqlDialect::MySql, 'INT2', 'smallint'];
        yield 'MySql-BIGINT' => [MySqlDialect::MySql, 'BIGINT', 'bigint'];
        yield 'MySql-INT8' => [MySqlDialect::MySql, 'INT8', 'bigint'];
        yield 'MySql-DEC' => [MySqlDialect::MySql, 'DEC', 'numeric'];
        yield 'MySql-DECIMAL' => [MySqlDialect::MySql, 'DECIMAL', 'numeric'];
        yield 'MySql-NUMERIC' => [MySqlDialect::MySql, 'NUMERIC', 'numeric'];
        yield 'MySql-REAL' => [MySqlDialect::MySql, 'REAL', 'double precision'];
        yield 'MySql-FLOAT4' => [MySqlDialect::MySql, 'FLOAT4', 'real'];
        yield 'MySql-DOUBLE' => [MySqlDialect::MySql, 'DOUBLE', 'double precision'];
        yield 'MySql-DOUBLE PRECISION' => [MySqlDialect::MySql, 'DOUBLE PRECISION', 'double precision'];
        yield 'MySql-FLOAT8' => [MySqlDialect::MySql, 'FLOAT8', 'double precision'];
        yield 'MySql-BOOL' => [MySqlDialect::MySql, 'BOOL', 'tinyint'];
        yield 'MySql-BOOLEAN' => [MySqlDialect::MySql, 'BOOLEAN', 'tinyint'];
        yield 'MySql-VARCHAR' => [MySqlDialect::MySql, 'VARCHAR', 'varchar'];
        yield 'MySql-CHARACTER VARYING' => [MySqlDialect::MySql, 'CHARACTER VARYING', 'varchar'];
        yield 'MySql-CHAR VARYING' => [MySqlDialect::MySql, 'CHAR VARYING', 'varchar'];
        yield 'MySql-CHAR' => [MySqlDialect::MySql, 'CHAR', 'char'];
        yield 'MySql-CHARACTER' => [MySqlDialect::MySql, 'CHARACTER', 'char'];
        yield 'MySql-TEXT' => [MySqlDialect::MySql, 'TEXT', 'text'];
        yield 'MySql-DATE' => [MySqlDialect::MySql, 'DATE', 'date'];
        yield 'MySql-TIME' => [MySqlDialect::MySql, 'TIME', 'time'];
        yield 'MySql-TIMESTAMP' => [MySqlDialect::MySql, 'TIMESTAMP', 'timestamp'];
        yield 'MySql-JSON' => [MySqlDialect::MySql, 'JSON', 'json'];
        yield 'MySql-TINYINT' => [MySqlDialect::MySql, 'TINYINT', 'tinyint'];
        yield 'MySql-MEDIUMINT' => [MySqlDialect::MySql, 'MEDIUMINT', 'mediumint'];
        yield 'MySql-DATETIME' => [MySqlDialect::MySql, 'DATETIME', 'datetime'];
        yield 'MySql-BLOB' => [MySqlDialect::MySql, 'BLOB', 'blob'];
        yield 'MySql-UUID' => [MySqlDialect::MySql, 'UUID', null];
        yield 'MySql-BYTEA' => [MySqlDialect::MySql, 'BYTEA', null];
        yield 'MySql-JSONB' => [MySqlDialect::MySql, 'JSONB', null];
        yield 'MySql-TIMESTAMPTZ' => [MySqlDialect::MySql, 'TIMESTAMPTZ', null];
        yield 'MySql-TIMETZ' => [MySqlDialect::MySql, 'TIMETZ', null];
        yield 'MySql-INTERVAL' => [MySqlDialect::MySql, 'INTERVAL', null];
        yield 'MySql-UNSUPPORTED' => [MySqlDialect::MySql, 'UNSUPPORTED', null];
    }

    #[DataProvider('providerAffinities')]
    public function testAffinityUsesSqlitePrecedence(string $input, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Core\Ast\TypeReader(SqliteDialect::Sqlite))->affinity($input));
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
}
