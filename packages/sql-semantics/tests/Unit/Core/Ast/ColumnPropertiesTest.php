<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
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
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
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
#[CoversClass(\SqlSemantics\Core\Analysis\SchemaAnalyzer::class)]
#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaChanges::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnGeneration::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[CoversClass(Schema::class)]
#[CoversClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[CoversClass(Writer::class)]
#[CoversClass(\SqlSemantics\Statement\Assertion::class)]
final class ColumnPropertiesTest extends TestCase
{
    public function testGenerationDoesNotConfuseDefaultCastsWithGeneratedColumns(): void
    {
        $state = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT DEFAULT CAST(1 AS INT))');
        self::assertNull($state->tables[0]->columns[0]->generation);
        self::assertNotNull($state->tables[0]->columns[0]->defaultExpression);
    }

    public function testGenerationPreservesVirtualAndIdentityKinds(): void
    {
        $state = (new Schema(SqliteDialect::Sqlite))->analyze('CREATE TABLE t (stored INT, x INT AS (stored + 1) VIRTUAL)');
        self::assertSame('virtual', $state->tables[0]->columns[1]->generation?->kind->value);
        $identity = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT GENERATED BY DEFAULT AS IDENTITY (START WITH 10))')->tables[0]->columns[0];
        self::assertSame('identity', $identity->generation?->kind->value);
        self::assertNull($identity->generation->expression);
    }

    public function testCollationKeepsQuotedNames(): void
    {
        $column = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (label TEXT COLLATE "C")')->tables[0]->columns[0];
        self::assertNotNull($column->collation);
        self::assertSame('COLLATE "C"', Writer::render($column->collation));
    }

    public function testAutoIncrementIsAnExplicitStateProperty(): void
    {
        $state = (new Schema(MySqlDialect::MySql))->analyze('CREATE TABLE t (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, note TEXT)');
        self::assertTrue($state->tables[0]->columns[0]->autoIncrement);
        self::assertFalse($state->tables[0]->columns[1]->autoIncrement);
        self::assertSame('BIGINT UNSIGNED', $state->tables[0]->columns[0]->type->name);
    }


    public function testGenerationKeepsNestedQueryStructureInsideTheColumn(): void
    {
        $state = (new Schema(MySqlDialect::MySql))->analyze('CREATE TABLE t (id INT AS ((SELECT 1)))');
        self::assertSame(['id'], array_column($state->tables[0]->columns, 'name'));
        self::assertNotNull($state->tables[0]->columns[0]->generation);
        self::assertNotNull($state->tables[0]->columns[0]->generation->expression);
        self::assertStringContainsString('SELECT 1', Writer::render($state->tables[0]->columns[0]->generation->expression));
    }

    public function testGenerationDoesNotTreatNamedDefaultsAsComputedColumns(): void
    {
        $column = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT CONSTRAINT d DEFAULT CAST(1 AS INT))')->tables[0]->columns[0];
        self::assertNull($column->generation);
        self::assertNotNull($column->defaultExpression);
    }

    public function testAutoIncrementDoesNotInspectDefaultExpressionIdentifiers(): void
    {
        $column = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT DEFAULT auto_increment())')->tables[0]->columns[0];
        self::assertFalse($column->autoIncrement);
        self::assertSame(\SqlSemantics\Core\Type\Nullability::MaybeNull, $column->nullability);
    }

}
