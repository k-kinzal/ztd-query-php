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

}
