<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
final class InsertionBinderTest extends TestCase
{
    public function testBindRetainsExplicitPositionMapping(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, name TEXT, optional INTEGER DEFAULT 7)'));
        $statement = $binder->bind("INSERT INTO t(name,id) VALUES('alice',DEFAULT)");
        self::assertNotNull($statement->insertion);
        self::assertSame(['name', 'id'], array_map(static fn ($column) => $column->binding?->column->name, $statement->insertion->columns));
        self::assertTrue($statement->insertion->explicitColumns);
        self::assertSame('default', $statement->rows[0][1]->kind->value);
        self::assertSame(['optional'], array_column($statement->insertion->omittedColumns, 'name'));
    }
    public function testBindRetainsMysqlDefaultPositions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INTEGER, b INTEGER)')))->bind('INSERT INTO t(b,a) VALUES(DEFAULT,2)');
        self::assertNotNull($statement->insertion);
        self::assertSame(['b', 'a'], array_map(static fn ($column) => $column->binding?->column->name, $statement->insertion->columns));
        self::assertSame('default', $statement->rows[0][0]->kind->value);
        self::assertSame('2', $statement->rows[0][1]->symbol);
    }
    public function testBindRetainsSqliteQueryMapping(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INTEGER, b TEXT)')))->bind("INSERT INTO t(b,a) SELECT 'x',1");
        self::assertNotNull($statement->insertion);
        self::assertSame(['b', 'a'], array_map(static fn ($column) => $column->binding?->column->name, $statement->insertion->columns));
        self::assertCount(1, $statement->queries);
        self::assertSame([], $statement->rows);
    }
    public function testBindResolvesImplicitPostgresPrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INTEGER, b INTEGER DEFAULT 2)')))->bind('INSERT INTO t VALUES(1)');
        self::assertNotNull($statement->insertion);
        self::assertFalse($statement->insertion->explicitColumns);
        self::assertCount(1, $statement->insertion->columns);
        self::assertSame(['b'], array_column($statement->insertion->omittedColumns, 'name'));
    }
    public function testBindRetainsDefaultRow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INTEGER DEFAULT 2)')))->bind('INSERT INTO t DEFAULT VALUES');
        self::assertNotNull($statement->insertion);
        self::assertTrue($statement->insertion->defaultValues);
        self::assertSame([], $statement->insertion->columns);
        self::assertSame(['a'], array_column($statement->insertion->omittedColumns, 'name'));
    }
    public function testBindDiagnosesUnknownDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)')))->bind('INSERT INTO t(missing) VALUES(1)', strict: false);
        self::assertNotNull($statement->insertion);
        self::assertSame(['missing'], $statement->insertion->columns[0]->reference);
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
    }
    public function testCheckRowRejectsWidthMismatch(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INTEGER, b INTEGER)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('different widths');
        $binder->bind('INSERT INTO t(a,b) VALUES(1)');
    }
    public function testBindRejectsRepeatedDestination(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INTEGER)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('more than once');
        $binder->bind('INSERT INTO t(a,a) VALUES(1,2)');
    }

    public function testDefaultValuesIgnoresLiteralContents(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(name TEXT)'));
        $values = $binder->bind("INSERT INTO t VALUES ('DEFAULT VALUES')")->insertion;
        $defaults = $binder->bind('INSERT INTO t DEFAULT VALUES')->insertion;
        self::assertNotNull($values);
        self::assertNotNull($defaults);
        self::assertFalse($values->defaultValues);
        self::assertCount(1, $values->columns);
        self::assertTrue($defaults->defaultValues);
    }
}
