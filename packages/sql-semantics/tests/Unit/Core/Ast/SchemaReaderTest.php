<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
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
        $schema = (new SchemaFacade($dialect))->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 1, UNIQUE(score), FOREIGN KEY (parent_id) REFERENCES users(id), CHECK (score > 0))');
        $table = $schema->tables[0];
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
        $builder = new SchemaFacade(PostgreSqlDialect::PostgreSql);
        $sql = 'CREATE TABLE users (id INTEGER)';
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $builder->analyze($sql, $sql);
    }

    public function testPrimaryKeysRejectsMissingColumn(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unknown column');
        (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE users (id INTEGER, PRIMARY KEY (missing))');
    }

    public function testColumnNodesPreservesSqliteDeclarationOrder(): void
    {
        $table = (new SchemaFacade(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (z INTEGER, a TEXT, m REAL)')->tables[0];
        self::assertSame(['z', 'a', 'm'], array_column($table->columns, 'name'));
    }

    public function testPrimaryNotNullRespectsSqliteDescException(): void
    {
        $table = (new SchemaFacade(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY DESC)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
    }

    public function testValidateRejectsCreateAsSelect(): void
    {
        $this->expectException(SemanticException::class);
        (new SchemaFacade(SqliteDialect::Sqlite))->analyze('CREATE TABLE users AS SELECT 1 AS id');
    }

    public function testPrimaryKeysResolvesCaseInsensitiveSqliteConstraintColumns(): void
    {
        $table = (new SchemaFacade(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (id INTEGER, PRIMARY KEY (ID))')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}
