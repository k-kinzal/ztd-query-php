<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Analyzer::class)]
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
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class TypeReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testReadPreservesDecimalPrecisionAndScale(Dialect $dialect): void
    {
        $table = (new AnalysisCase($dialect))->schema('CREATE TABLE users (amount DECIMAL(10, 2))')->tables[0];
        self::assertSame(['10', '2'], $table->columns[0]->type->modifiers);
    }
    public function testAffinityPreservesSqliteDeclaredTypes(): void
    {
        $table = (new AnalysisCase(Dialect::Sqlite))->schema('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame('text', $table->columns[0]->type->affinity);
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
}
