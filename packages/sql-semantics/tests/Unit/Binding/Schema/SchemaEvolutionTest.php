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

#[CoversClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
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
#[CoversClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
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
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
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
#[UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
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
final class SchemaEvolutionTest extends TestCase
{
    public function testCreateDerivesQueryColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, n INTEGER GENERATED ALWAYS AS (id + 1) STORED)', 'CREATE TABLE u AS SELECT id, n FROM t', 'CREATE VIEW v AS SELECT n FROM u', 'DROP TABLE u');
        self::assertSame(['t', 'v'], array_column($schema->tables, 'name'));
        self::assertSame('n', $schema->tables[1]->columns[0]->name);
        self::assertNotNull($schema->tables[0]->columns[1]->generatedExpression);
        self::assertCount(4, $schema->statements);
    }
    public function testBuildAcceptsTableOptions(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
        self::assertSame('id', $schema->tables[0]->columns[0]->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $schema->tables[0]->columns[0]->nullability);
    }

    public function testApplyCreateLike(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INTEGER PRIMARY KEY)', 'CREATE TABLE u LIKE t');
        self::assertSame('id', $schema->tables[1]->columns[0]->name);
        self::assertSame('integer', $schema->tables[1]->columns[0]->type->name);
    }

    public function testApplyIdempotentCreateAndQualifiedDrop(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create table app.t (id integer)', 'create table if not exists app.t (wrong text)', 'create table other.t (n text)', 'drop table app.t');
        self::assertCount(1, $schema->tables);
        self::assertSame('other', $schema->tables[0]->schema);
        self::assertSame(['n'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testApplyReplacesAViewDefinition(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create view v as select 1 as a', 'create or replace view v as select 2 as a, 3 as b');
        self::assertCount(1, $schema->tables);
        self::assertSame(['a','b'], array_column($schema->tables[0]->columns, 'name'));
    }



    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite])]
    public function testCreateViewUsesDeclaredOutputNames(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (n INTEGER)', 'CREATE VIEW v(x,y) AS SELECT n, 2 FROM t');
        self::assertSame(['x', 'y'], array_column($schema->tables[1]->columns, 'name'));
        self::assertSame(['integer', 'integer'], array_map(static fn ($column): string => $column->type->name, $schema->tables[1]->columns));
        self::assertSame(['maybe-null', 'not-null'], array_map(static fn ($column): string => $column->nullability->value, $schema->tables[1]->columns));
        $query = (new Binder($schema))->bind('SELECT y FROM v WHERE x>0');
        self::assertSame('y', $query->outputs[0]->name);
        self::assertSame('>', $query->where?->symbol);
    }


    public function testApplyReportsDuplicateDeclarations(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('Duplicate table declaration: t');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)', 'CREATE TABLE t (n TEXT)');
    }

}
