<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
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
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
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
final class MutationBinderTest extends TestCase
{
    public function testBindReturnsAssignmentPredicateAndReturningColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t SET n=n+1 WHERE id=2 RETURNING id,n');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame('UPDATE', $statement->kind->value);
        self::assertSame('+', $statement->writes[0]->value->spelling());
        self::assertSame('=', $statement->where?->spelling());
        self::assertSame(['id', 'n'], array_column($statement->outputs, 'name'));
    }
    public function testTargetsResolvesInsertTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (n INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(n) VALUES(1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame('INSERT', $statement->kind->value);
        self::assertSame('t', $statement->affectedTables()[0]->declaration->name);
    }

    public function testConflictScopeResolvesExcludedValues(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) DO UPDATE SET n=excluded.n RETURNING id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $query);
        self::assertSame('n', $query->conflicts[0]->assignments[0]->value->columnBinding()?->column->name);
        self::assertNotSame($query->affectedTables()[0]->id, $query->conflicts[0]->assignments[0]->value->columnBinding()->relationId);
    }

    public function testBindUpdateFromKeepsReadAndWriteRoles(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)', 'CREATE TABLE u (id INTEGER, value INTEGER)');
        $query = (new Binder($schema))->bind('UPDATE t SET n=u.value FROM u WHERE t.id=u.id RETURNING t.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $query);
        self::assertSame('UPDATE', $query->kind->value);
        self::assertSame('t', $query->affectedTables()[0]->declaration->name);
        self::assertSame(['t', 'u'], array_map(static fn ($relation): string => $relation->declaration->name, [$query->target, $query->from]));
        self::assertSame('u', $query->writes[0]->value->columnBinding()?->table->name);
        self::assertSame('=', $query->where?->spelling());
        self::assertSame('id', $query->outputs[0]->name);
    }

    public function testBindUsesCtesWithoutConfusingAssignmentTargets(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $query = (new Binder($schema))->bind('WITH source AS (SELECT id,n FROM t) UPDATE t SET n=source.n FROM source WHERE t.id=source.id RETURNING t.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $query);
        self::assertSame('UPDATE', $query->kind->value);
        self::assertSame(['source'], array_column($query->ctes->definitions, 'name'));
        self::assertSame(['n'], array_map(static fn ($write) => $write->destinations()[0]->column()->referenceParts()[0], $query->writes));
        self::assertSame('source', $query->writes[0]->value->columnBinding()?->table->name);
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertSame('=', $query->where?->spelling());
    }

    public function testBindKeepsLegacyInsertQuerySeparateFromItsTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE target (id INTEGER)', 'CREATE TABLE source (id INTEGER)');
        $query = (new Binder($schema))->bind('INSERT INTO target(id) (SELECT id FROM source)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $query);
        self::assertSame(['target'], array_map(static fn ($target): string => $target->declaration->name, $query->affectedTables()));

        self::assertSame('source', $query->query->relations[0]->declaration->name);
        self::assertSame(['id'], array_column($query->query->outputs, 'name'));
        self::assertNotSame($query->scopeId, $query->query->scopeId);
    }

    public function testAssignmentsRetainsTheScalarDestinationAndValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER)')))->bind('UPDATE t SET n=2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame('n', $statement->writes[0]->destinations()[0]->column()->referenceParts()[0]);
        self::assertSame('2', $statement->writes[0]->value->spelling());
    }


    public function testInputRetainsTargetAndUsingJoinForDelete(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteUsingStatement::class, $statement);
        self::assertSame('t', $statement->target->declaration->name);
        self::assertSame('s', $statement->using->declaration->name);
        self::assertSame([$statement->target], $statement->affectedTables());
    }
    public function testDeleteTargetsKeepsOnlyNamedMysqlAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE a FROM t AS a JOIN t AS b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        self::assertCount(2, [$statement->from->left, $statement->from->right]);
        self::assertSame(['a'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame($statement->from->left, $statement->affectedTables()[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame('=', $statement->from->condition?->spelling());
    }
    public function testDeleteTargetsRetainsLegacyJoinAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE a FROM t AS a JOIN t AS b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        self::assertSame(['a'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame(['a','b'], array_column([$statement->from->left, $statement->from->right], 'alias'));
    }

    public function testUpdatedTargetsExcludesReadOnlyJoinInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t a JOIN t b ON a.id=b.id SET a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame(['a'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame(['a','b'], array_column([$statement->from->left, $statement->from->right], 'alias'));
        self::assertSame($statement->from->right->id, $statement->writes[0]->value->columnBinding()?->relationId);
    }
    public function testUpdatedTargetsPreservesMultipleWrittenAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t a JOIN t b ON a.id=b.id SET a.id=1,b.id=2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame(['a','b'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame(['1','2'], array_map(static fn ($write) => $write->value->spelling(), $statement->writes));
    }
    public function testUpdatedTargetsRetainsQualifiedUnresolvedDestinations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('UPDATE missing a JOIN missing b ON a.id=b.id SET a.id=1', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame(['a'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame(['a','id'], $statement->writes[0]->destinations()[0]->column()->referenceParts());
    }
    public function testUpdatedTargetsRetainsCandidatesForUnqualifiedUnknownColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('UPDATE missing a JOIN missing b ON a.id=b.id SET value=1', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame(['a','b'], array_column($statement->affectedTables(), 'alias'));
        self::assertSame(['value'], $statement->writes[0]->destinations()[0]->column()->referenceParts());
    }
    public function testBindDiagnosesPaginationOnAMysqlJoinedUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind('UPDATE t a JOIN t b ON a.id=b.id SET a.id=1 LIMIT 2', strict: false);
    }

}
