<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
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
#[CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
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
final class SchemaReaderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testReadDeclaredKeysDefaultsAndChecks(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 1, UNIQUE(score), FOREIGN KEY (parent_id) REFERENCES users(id), CHECK (score > 0))');
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
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $sql = 'CREATE TABLE users (id INTEGER)';
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $builder->build($sql, $sql);
    }

    public function testPrimaryKeysRejectsMissingColumn(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unknown column');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER, PRIMARY KEY (missing))');
    }

    public function testColumnNodesPreservesSqliteDeclarationOrder(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (z INTEGER, a TEXT, m REAL)')->tables[0];
        self::assertSame(['z', 'a', 'm'], array_column($table->columns, 'name'));
    }

    public function testPrimaryNotNullRespectsSqliteDescException(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id INTEGER PRIMARY KEY DESC)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
    }

    public function testValidateRejectsCreateAsSelect(): void
    {
        $this->expectException(SemanticException::class);
        (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users AS SELECT 1 AS id');
    }

    public function testPrimaryKeysResolvesCaseInsensitiveSqliteConstraintColumns(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id INTEGER, PRIMARY KEY (ID))')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}
