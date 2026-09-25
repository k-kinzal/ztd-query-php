<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Dialect;

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
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testReadPreservesDecimalPrecisionAndScale(Dialect $dialect): void
    {
        $table = (new SchemaBuilder($dialect))->build('CREATE TABLE users (amount DECIMAL(10, 2))')->tables[0];
        self::assertSame(['10', '2'], $table->columns[0]->type->modifiers);
    }

    public function testAffinityPreservesSqliteDeclaredTypes(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id TEXT PRIMARY KEY, score INTEGER)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertSame('text', $table->columns[0]->type->affinity);
    }

    public function testCanonicalRejectsUnsupportedDomainNames(): void
    {
        $reader = new \SqlSemantics\Core\Ast\TypeReader(Dialect::PostgreSql);
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
        self::assertSame($expected, (new \SqlSemantics\Core\Ast\TypeReader(Dialect::Sqlite))->affinity($input));
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
