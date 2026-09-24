<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[UsesClass(\SqlSemantics\Serializer::class)]
#[UsesClass(\SqlSemantics\StatementFactory::class)]
#[UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class TableAlterationTest extends TestCase
{
    public function testApplyAddsRenamesAndDropsColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)', 'ALTER TABLE t ADD COLUMN n INTEGER DEFAULT 2', 'ALTER TABLE t RENAME COLUMN n TO value', 'ALTER TABLE t DROP COLUMN id', 'ALTER TABLE t RENAME TO renamed');
        self::assertSame('renamed', $schema->tables[0]->name);
        self::assertSame(['value'], array_column($schema->tables[0]->columns, 'name'));
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $schema->tables[0]->columns[0]->generation);
        self::assertNotNull($schema->tables[0]->columns[0]->generation->default);
    }
    public function testActionKeepsUnaffectedColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER, n TEXT)', 'ALTER TABLE t RENAME COLUMN id TO key');
        self::assertSame(['key', 'n'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testColumnAttributesUpdatesTypeAndNullability(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)', 'ALTER TABLE t ALTER COLUMN n TYPE NUMERIC(10,2), ALTER COLUMN n SET NOT NULL');
        self::assertSame('numeric', $schema->tables[0]->columns[0]->type->name);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericStorage::class, $schema->tables[0]->columns[0]->type->identity);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericParameter::class, $schema->tables[0]->columns[0]->type->identity->precision);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericParameter::class, $schema->tables[0]->columns[0]->type->identity->scale);
        self::assertSame('10', $schema->tables[0]->columns[0]->type->identity->precision->spelling);
        self::assertSame('2', $schema->tables[0]->columns[0]->type->identity->scale->spelling);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $schema->tables[0]->columns[0]->nullability);
    }

    public function testActionRetainsUnchangedColumnsAndOtherTables(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t (id integer not null default 1, value text)', 'create table u (id integer)', 'alter table t alter column id drop not null, alter column id set default 2');
        self::assertSame('maybe-null', $schema->tables[0]->columns[0]->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $schema->tables[0]->columns[0]->generation);
        self::assertNotNull($schema->tables[0]->columns[0]->generation->default);
        self::assertSame('2', $schema->tables[0]->columns[0]->generation->default->spelling());
        self::assertSame('text', $schema->tables[0]->columns[1]->type->name);
        self::assertSame(['id'], array_column($schema->tables[1]->columns, 'name'));
        $withoutDefault = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t (id integer default 1)', 'alter table t alter column id drop default');
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $withoutDefault->tables[0]->columns[0]->generation);
        self::assertNull($withoutDefault->tables[0]->columns[0]->generation->default);
    }



    public function testApplySqliteColumnAdditionKeepsItsAttributes(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER)', 'ALTER TABLE t ADD COLUMN n TEXT NOT NULL DEFAULT \'value\'');
        self::assertSame(['id','n'], array_column($schema->tables[0]->columns, 'name'));
        self::assertSame('text', $schema->tables[0]->columns[1]->type->name);
        self::assertSame('not-null', $schema->tables[0]->columns[1]->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $schema->tables[0]->columns[1]->generation);
        self::assertNotNull($schema->tables[0]->columns[1]->generation->default);
    }

    public function testApplyBindsTheCheckOfAnAddedColumnAgainstTheQualifiedTable(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)', 'ALTER TABLE t ADD COLUMN n INTEGER CHECK (public.t.id > 0)');
        self::assertSame(['id', 'n'], array_column($schema->tables[0]->columns, 'name'));
        self::assertCount(1, $schema->tables[0]->constraints);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $schema->tables[0]->constraints[0]);
        self::assertSame('>', $schema->tables[0]->constraints[0]->predicate->spelling());
    }

    public function testApplyReadsLowerCaseRenamesAndDropsWithoutColumnKeyword(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER, k INTEGER)', 'alter table t rename n to m', 'alter table t drop id');
        self::assertSame(['m', 'k'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testApplyKeepsAColumnNamedLikeTheDroppedKeyword(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER DEFAULT 1, "constraint" INTEGER, "default" INTEGER, CONSTRAINT k CHECK (id > 0))', 'ALTER TABLE t DROP CONSTRAINT k', 'ALTER TABLE t ALTER id DROP DEFAULT');
        self::assertSame(['id', 'constraint', 'default'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testColumnAttributesKeepsTheColumnsBeforeTheAlteredOne(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)', 'ALTER TABLE t ALTER COLUMN n TYPE bigint');
        self::assertSame(['id', 'n'], array_column($schema->tables[0]->columns, 'name'));
        self::assertSame(['integer', 'bigint'], array_map(static fn ($column): string => $column->type->name, $schema->tables[0]->columns));
    }

    public function testActionReturnsTheRenamedTableName(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $alteration = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $action = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t RENAME TO u'), ['RenameStmt'])[0];
        [$columns, $name] = $alteration->action($action, $schema->tables[0]->columns, 't');
        self::assertSame('u', $name);
        self::assertSame(['id'], array_column($columns, 'name'));
    }

    public function testColumnAttributesLeavesOtherOperationsAlone(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $alteration = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $action = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER COLUMN id SET NOT NULL'), ['alter_table_cmd'])[0];
        self::assertSame('not-null', $alteration->columnAttributes($action, $schema->tables[0]->columns)[0]->nullability->value);
        self::assertSame($schema->tables[0]->columns, $alteration->columnAttributes(\SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER CONSTRAINT k DEFERRABLE'), ['alter_table_cmd'])[0], $schema->tables[0]->columns));
    }


    public function testApplyReadsColumnChangesOutsideTheDefaultExpression(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a text NOT NULL)', "ALTER TABLE t ALTER COLUMN a SET DEFAULT 'DROP NOT NULL'");
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $schema->tables[0]->columns[0]->nullability);
    }
}
