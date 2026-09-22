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
final class MutationBinderTest extends TestCase
{
    public function testBindReturnsAssignmentPredicateAndReturningColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t SET n=n+1 WHERE id=2 RETURNING id,n');
        self::assertSame('UPDATE', $statement->kind);
        self::assertSame('+', $statement->assignments['n']->symbol);
        self::assertSame('=', $statement->where?->symbol);
        self::assertSame(['id', 'n'], array_column($statement->outputs, 'name'));
    }
    public function testTargetsResolvesInsertTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (n INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(n) VALUES(1)');
        self::assertSame('INSERT', $statement->kind);
        self::assertSame('t', $statement->targets[0]->declaration->name);
    }

    public function testConflictScopeResolvesExcludedValues(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) DO UPDATE SET n=excluded.n RETURNING id');
        self::assertSame('n', $query->assignments['n']->binding?->column->name);
        self::assertNotSame($query->targets[0]->id, $query->assignments['n']->binding->relationId);
    }

    public function testBindUpdateFromKeepsReadAndWriteRoles(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)', 'CREATE TABLE u (id INTEGER, value INTEGER)');
        $query = (new Binder($schema))->bind('UPDATE t SET n=u.value FROM u WHERE t.id=u.id RETURNING t.id');
        self::assertSame('UPDATE', $query->kind);
        self::assertSame('t', $query->targets[0]->declaration->name);
        self::assertSame(['t', 'u'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertSame('u', $query->assignments['n']->binding?->table->name);
        self::assertSame('=', $query->where?->symbol);
        self::assertSame('id', $query->outputs[0]->name);
    }

    public function testBindUsesCtesWithoutConfusingAssignmentTargets(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $query = (new Binder($schema))->bind('WITH source AS (SELECT id,n FROM t) UPDATE t SET n=source.n FROM source WHERE t.id=source.id RETURNING t.id');
        self::assertSame('UPDATE', $query->kind);
        self::assertSame(['source'], array_keys($query->ctes));
        self::assertSame(['n'], array_keys($query->assignments));
        self::assertSame('source', $query->assignments['n']->binding?->table->name);
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertSame('=', $query->where?->symbol);
    }

    public function testBindKeepsLegacyInsertQuerySeparateFromItsTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE target (id INTEGER)', 'CREATE TABLE source (id INTEGER)');
        $query = (new Binder($schema))->bind('INSERT INTO target(id) (SELECT id FROM source)');
        self::assertSame(['target'], array_map(static fn ($target): string => $target->declaration->name, $query->targets));
        self::assertCount(1, $query->queries);
        self::assertSame('source', $query->queries[0]->relations[0]->declaration->name);
        self::assertSame(['id'], array_column($query->queries[0]->outputs, 'name'));
        self::assertNotSame($query->scopeId, $query->queries[0]->scopeId);
    }

    public function testAssignmentsRetainsTheScalarCompatibilityView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER)')))->bind('UPDATE t SET n=2');
        self::assertSame($statement->writes[0]->value, $statement->assignments['n']);
    }


    public function testInputRetainsTargetAndUsingJoinForDelete(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame('cross', $statement->from->kind->value);
        self::assertSame($statement->targets[0], $statement->from->left);
        self::assertSame(['t','s'], array_map(static fn ($relation) => $relation->declaration->name, $statement->relations));
    }
    public function testDeleteTargetsKeepsOnlyNamedMysqlAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE a FROM t AS a JOIN t AS b ON a.id=b.id');
        self::assertCount(2, $statement->relations);
        self::assertSame(['a'], array_column($statement->targets, 'alias'));
        self::assertSame($statement->relations[0], $statement->targets[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame('=', $statement->from->condition?->symbol);
    }
    public function testDeleteTargetsRetainsLegacyJoinAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE a FROM t AS a JOIN t AS b ON a.id=b.id');
        self::assertSame(['a'], array_column($statement->targets, 'alias'));
        self::assertSame(['a','b'], array_column($statement->relations, 'alias'));
    }

    public function testUpdatedTargetsExcludesReadOnlyJoinInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t a JOIN t b ON a.id=b.id SET a.id=b.id');
        self::assertSame(['a'], array_column($statement->targets, 'alias'));
        self::assertSame(['a','b'], array_column($statement->relations, 'alias'));
        self::assertSame($statement->relations[1]->id, $statement->writes[0]->value->binding?->relationId);
    }
    public function testUpdatedTargetsPreservesMultipleWrittenAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t a JOIN t b ON a.id=b.id SET a.id=1,b.id=2');
        self::assertSame(['a','b'], array_column($statement->targets, 'alias'));
        self::assertSame(['1','2'], array_map(static fn ($write) => $write->value->symbol, $statement->writes));
    }
    public function testUpdatedTargetsRetainsQualifiedUnresolvedDestinations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('UPDATE missing a JOIN missing b ON a.id=b.id SET a.id=1', strict: false);
        self::assertSame(['a'], array_column($statement->targets, 'alias'));
        self::assertSame(['a','id'], $statement->writes[0]->targets[0]->reference);
    }
    public function testUpdatedTargetsRetainsCandidatesForUnqualifiedUnknownColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('UPDATE missing a JOIN missing b ON a.id=b.id SET value=1', strict: false);
        self::assertSame(['a','b'], array_column($statement->targets, 'alias'));
        self::assertSame(['value'], $statement->writes[0]->targets[0]->reference);
    }
}
