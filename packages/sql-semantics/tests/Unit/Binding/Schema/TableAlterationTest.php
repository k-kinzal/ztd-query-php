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

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-5.6.51', 'ALTER TABLE t ADD c INT PRIMARY KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'ALTER TABLE t ADD c INT PRIMARY KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-9.1.0', 'ALTER TABLE t ADD COLUMN c INT KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'ALTER TABLE t ADD (c INT PRIMARY KEY)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'pg-17.2', 'ALTER TABLE t ADD COLUMN c int PRIMARY KEY'])]
    public function testApplyAddsAColumnWithItsPrimaryKey(Dialect $dialect, string $version, string $alter): void
    {
        $table = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t (a INT)', $alter)->tables[0];
        self::assertSame(['a', 'c'], array_column($table->columns, 'name'));
        self::assertSame([\SqlSemantics\Type\Nullability::MaybeNull, \SqlSemantics\Type\Nullability::NotNull], array_column($table->columns, 'nullability'));
        self::assertCount(1, $table->constraints);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $table->constraints[0]);
        self::assertSame(['c'], $table->constraints[0]->localColumns());
    }

    /**
     * @param class-string<\SqlSemantics\Schema\TableConstraint> $kind
     */
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'ALTER TABLE t ADD COLUMN c INT UNIQUE', \SqlSemantics\Schema\Constraint\UniqueKey::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'ALTER TABLE t ADD COLUMN c INT CHECK (c > a)', \SqlSemantics\Schema\Constraint\Check::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-9.1.0', 'ALTER TABLE t ADD COLUMN c INT REFERENCES t (a)', \SqlSemantics\Schema\Constraint\ForeignKey::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'pg-17.2', 'ALTER TABLE t ADD COLUMN c int UNIQUE', \SqlSemantics\Schema\Constraint\UniqueKey::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'pg-17.2', 'ALTER TABLE t ADD COLUMN c int CHECK (c > a)', \SqlSemantics\Schema\Constraint\Check::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'pg-17.2', 'ALTER TABLE t ADD COLUMN c int REFERENCES t (a)', \SqlSemantics\Schema\Constraint\ForeignKey::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'sqlite-3.47.2', 'ALTER TABLE t ADD COLUMN c INT CHECK (c > a)', \SqlSemantics\Schema\Constraint\Check::class])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'sqlite-3.47.2', 'ALTER TABLE t ADD COLUMN c INT REFERENCES t (a)', \SqlSemantics\Schema\Constraint\ForeignKey::class])]
    public function testApplyBindsTheConstraintsOfAnAddedColumnAgainstIt(Dialect $dialect, string $version, string $alter, string $kind): void
    {
        $table = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t (a INT)', $alter)->tables[0];
        self::assertSame(['a', 'c'], array_column($table->columns, 'name'));
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $table->columns[1]->nullability);
        self::assertCount(1, $table->constraints);
        self::assertInstanceOf($kind, $table->constraints[0]);
    }

    /**
     * @param list<string> $names
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'ALTER TABLE t MODIFY a INT NULL PRIMARY KEY', ['a', 'b'], 0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t MODIFY COLUMN a BIGINT PRIMARY KEY', ['a', 'b'], 0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t CHANGE a c INT PRIMARY KEY', ['c', 'b'], 0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'ALTER TABLE t CHANGE COLUMN t.a c INT KEY AFTER b', ['b', 'c'], 1])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0', 'ALTER TABLE t MODIFY b INT PRIMARY KEY FIRST', ['b', 'a'], 0])]
    public function testApplyRedeclaresAMySqlColumnWithItsKey(string $version, string $alter, array $names, int $key): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT, b INT)', $alter)->tables[0];
        self::assertSame($names, array_column($table->columns, 'name'));
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $table->columns[$key]->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $table->columns[1 - $key]->nullability);
        self::assertCount(1, $table->constraints);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $table->constraints[0]);
        self::assertSame([$names[$key]], $table->constraints[0]->localColumns());
    }

    public function testApplyReplacesTheWholeMySqlDeclaration(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT NOT NULL DEFAULT 1, b INT)', 'ALTER TABLE t MODIFY a BIGINT UNIQUE, CHANGE b c INT NULL')->tables[0];
        self::assertSame(['a', 'c'], array_column($table->columns, 'name'));
        self::assertSame(['bigint', 'integer'], array_map(static fn ($column): string => $column->type->name, $table->columns));
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $table->columns[0]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $table->columns[0]->generation);
        self::assertNull($table->columns[0]->generation->default);
        self::assertTrue($table->columns[1]->nullDeclared);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $table->constraints[0]);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER TABLE t ADD c INT PRIMARY KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER TABLE t ADD COLUMN c INT UNIQUE'])]
    public function testApplyRejectsASqliteAddedColumnKey(string $alter): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::AddedColumnKey->message());
        (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)', $alter);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER TABLE t ADD c INT NULL PRIMARY KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER TABLE t MODIFY a INT NULL, ADD PRIMARY KEY (a)'])]
    public function testApplyRejectsANullablePrimaryKeyPart(string $alter): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::NullablePrimaryKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build('CREATE TABLE t (a INT)', $alter);
    }

    public function testAttributesReadsTheColumnAttributesOfEachDialect(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)');
        $alteration = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite), 'main'));
        $statement = (new \SqlSemantics\Ast\DialectParser(Dialect::Sqlite))->parse('ALTER TABLE t ADD c INT NOT NULL DEFAULT 1');
        $column = \SqlSemantics\Binding\Schema\Alter\AddedColumns::read($statement)[0];
        self::assertSame(['NOT NULL', 'DEFAULT 1'], array_map(\SqlSemantics\Ast\Tree::text(...), $alteration->attributes($statement, $column)));
        $pg = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ADD c int NOT NULL UNIQUE');
        self::assertSame(['NOT NULL', 'UNIQUE'], array_map(\SqlSemantics\Ast\Tree::text(...), $alteration->attributes($pg, \SqlSemantics\Binding\Schema\Alter\AddedColumns::read($pg)[0])));
    }

    public function testScopeResolvesTheGivenColumnsUnderTheQualifiedTableName(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int)');
        $alteration = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $table = $schema->tables[0];
        $scope = $alteration->scope($table, $table->source, [...$table->columns, $table->columns[0]->withName('c')]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $scope->column(['public', 't', 'c'], $table->source));
        self::assertSame(['a'], array_column($table->columns, 'name'));
    }

    public function testPrimaryKeysDeclaresKeyColumnsNotNullOutsideSqlite(): void
    {
        $pg = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, b int)', 'CREATE TABLE k (a int PRIMARY KEY)');
        $alteration = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($pg, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        self::assertSame([\SqlSemantics\Type\Nullability::NotNull, \SqlSemantics\Type\Nullability::MaybeNull], array_column($alteration->primaryKeys($pg->tables[0]->columns, $pg->tables[1]->constraints), 'nullability'));
        $sqlite = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a TEXT PRIMARY KEY)');
        $kept = new \SqlSemantics\Binding\Schema\TableAlteration(new \SqlSemantics\Binding\TableResolver($sqlite, new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite), 'main'));
        self::assertSame($sqlite->tables[0]->columns, $kept->primaryKeys($sqlite->tables[0]->columns, $sqlite->tables[0]->constraints));
    }
}
