<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Analysis::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
final class FromBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testJoinedBindsOnBeforeIntroducingThisJoinsNulls(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT b.score FROM users a LEFT JOIN users b ON a.id=b.id WHERE b.score > 0');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertNotNull($statement->from->condition);
        self::assertSame(Nullability::NotNull, $statement->from->condition->operands[1]->nullability);
        self::assertNotNull($statement->where);
        self::assertSame(Nullability::MaybeNull, $statement->where->operands[0]->nullability);
        self::assertSame(['j0'], $statement->where->operands[0]->nullExtendedBy);
    }

    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j1', 'j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
    }

    public function testRelationFullJoinExtendsBothSides(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindCrossJoinHasNoMatchPredicate(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id FROM users a CROSS JOIN users b');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame(\SqlSemantics\Model\JoinKind::Cross, $statement->from->kind);
        self::assertNull($statement->from->condition);
    }

    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $builder = new SchemaBuilder(Dialect::Sqlite);
        $schema = $builder->build('CREATE TABLE main.users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT u.id FROM main.users AS u');
        self::assertSame('main', $statement->relations[0]->declaration->schema);
    }

    public function testTableAppliesAliasColumnLists(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n TEXT)');
        $query = (new Binder($schema))->bind('SELECT q.key, q.value FROM t AS q(key, value)');
        self::assertSame(['key', 'value'], array_column($query->outputs, 'name'));
        self::assertSame('text', $query->outputs[1]->expression->type->name);
    }

    public function testKindRecognizesRightAndNatural(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $tables = new \SqlSemantics\Binding\TableResolver($builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $reader = new \SqlSemantics\Binding\FromBinder($tables, new \SqlSemantics\Binding\IdentitySequence());
        $source = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1');
        self::assertSame(\SqlSemantics\Model\JoinKind::Right, $reader->kind('RIGHT OUTER JOIN', $source));
        self::assertSame(\SqlSemantics\Model\JoinKind::Inner, $reader->kind('NATURAL JOIN', $source));
    }
    public function testDerivedPreservesLateralCorrelation(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.v FROM t CROSS JOIN LATERAL (SELECT t.n + 1 AS v) q');
        self::assertSame('n', $query->outputs[0]->expression->lineage()[1]->column->name);
    }

    public function testSqliteInputKeepsTheLeftSideOfADerivedJoin(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, q.n FROM t a JOIN (SELECT 1 AS n) q ON a.id=q.n');
        self::assertCount(2, $query->relations);
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDerivedKeepsItsInnerJoinInTheNestedScope(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.id FROM (SELECT a.id FROM t a JOIN t b ON a.id=b.id) q');
        self::assertCount(1, $query->relations);
        self::assertNotNull($query->relations[0]->query);
        self::assertCount(2, $query->relations[0]->query->relations);
        self::assertSame('id', $query->outputs[0]->name);
    }

    public function testTableResolvesCaseInsensitiveSqliteCtes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('WITH Mixed(Value) AS (SELECT 1) SELECT value FROM mixed');
        self::assertSame('Value', $query->outputs[0]->name);
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }


    public function testSqliteInputBindsParenthesizedJoins(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.id FROM (a JOIN b ON a.id=b.id)');
        self::assertSame(['a','b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertSame(['id','id'], array_column($query->outputs, 'name'));
    }

    public function testExplicitBindsTableQueryAsAnOrderedProjection(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, name TEXT)');
        $query = (new Binder($schema))->bind('TABLE t');
        self::assertSame(['id', 'name'], array_column($query->outputs, 'name'));
        self::assertSame($schema->tables[0], $query->relations[0]->declaration);
        self::assertSame('id', $query->outputs[0]->expression->binding?->column->name);
    }

    public function testDerivedBindsLegacySelectFactorInItsOwnScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM SELECT id FROM t');
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertNotNull($query->relations[0]->query);
        self::assertSame('t', $query->relations[0]->query->relations[0]->declaration->name);
        self::assertNotSame($query->scopeId, $query->relations[0]->query->scopeId);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testGroupedKeepsParenthesizedJoinsAsRelations(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.n FROM (a JOIN b ON a.id=b.n)');
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertSame(['a', 'b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertNull($query->relations[0]->query);
    }

}
