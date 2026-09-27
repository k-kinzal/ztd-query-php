<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
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

#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
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
final class SchemaReaderTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testReadDeclaredKeysDefaultsAndChecks(Dialect $dialect): void
    {
        $schema = Resolved::of((new Semantics($dialect))->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 1, UNIQUE(score), FOREIGN KEY (parent_id) REFERENCES users(id), CHECK (score > 0))', []));
        $table = $schema->declarations[0];
        self::assertSame(['id', 'parent_id', 'score'], array_column($table->columns, 'name'));
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
        self::assertNotNull($table->columns[2]->defaultExpression);
        self::assertSame(4, count($table->constraints));
        self::assertSame(['parent_id'], $table->constraints[2]->columns);
        self::assertSame(['users'], $table->constraints[2]->referencedTable);
        self::assertSame(['id'], $table->constraints[2]->referencedColumns);
        self::assertNotNull($table->constraints[3]->expression);
    }

    public function testTableRejectsDuplicateDeclarations(): void
    {
        $builder = new Semantics(PostgreSqlDialect::PostgreSql);
        $sql = 'CREATE TABLE users (id INTEGER)';
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $builder->analyze($sql, [$builder->analyze($sql)]);
    }

    public function testPrimaryKeysRejectsMissingColumn(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unknown column');
        Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE users (id INTEGER, PRIMARY KEY (missing))', []));
    }

    public function testColumnNodesPreservesSqliteDeclarationOrder(): void
    {
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (z INTEGER, a TEXT, m REAL)', []))->declarations[0];
        self::assertSame(['z', 'a', 'm'], array_column($table->columns, 'name'));
    }

    public function testPrimaryNotNullRespectsSqliteDescException(): void
    {
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY DESC)', []))->declarations[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
    }

    public function testValidateLeavesCreateAsSelectWithoutAReadableTable(): void
    {
        $resolution = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users AS SELECT 1 AS id', []));
        self::assertSame([], $resolution->declarations);
        self::assertSame(\SqlSemantics\Statement\ReferenceKind::Declaration, $resolution->references[0]->kind);
        self::assertNull($resolution->references[0]->table);
    }

    public function testPrimaryKeysResolvesCaseInsensitiveSqliteConstraintColumns(): void
    {
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id INTEGER, PRIMARY KEY (ID))', []))->declarations[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}
