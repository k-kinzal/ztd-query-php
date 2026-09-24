<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\AliasedRelation;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AliasedRelation::class)]
#[Medium]
final class AliasedRelationTest extends TestCase
{
    public function testExposesTheJoinOutputsBehindTheResultAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $binder = new Binder($schema);
        $query = $binder->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(AliasedRelation::class, $relation);
        self::assertSame('q', $relation->alias);
        self::assertSame(['a', 'b'], $relation->columnAliases);
        self::assertInstanceOf(OnJoin::class, $relation->input);
        self::assertSame(['id', 'id'], array_column($relation->outputs, 'name'));
        self::assertSame(array_column($relation->outputs, 'expression'), $relation->resultExpressions());
        self::assertSame(['a', 'b'], array_column($relation->declaration->columns, 'name'));
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testResultExpressionsFollowTheOutputsInOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a FROM (t a JOIN t b ON a.id=b.id) AS q(a,b,c,d)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(AliasedRelation::class, $relation);
        $expressions = $relation->resultExpressions();
        self::assertCount(4, $expressions);
        self::assertSame(array_column($relation->outputs, 'expression'), $expressions);
        self::assertSame(['id', 'n', 'id', 'n'], array_map(static fn ($expression): ?string => $expression->columnBinding()?->column->name, $expressions));
        self::assertSame(['r0', 'r0', 'r1', 'r1'], array_map(static fn ($expression): ?string => $expression->columnBinding()?->relationId, $expressions));
    }

    public function testWithScopeRetainsTheInputAndOutputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(AliasedRelation::class, $relation);
        $moved = $relation->withScope('s9');
        self::assertNotSame($relation, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertSame($relation->scopeId, $query->scopeId);
        self::assertSame($relation->id, $moved->id);
        self::assertSame($relation->input, $moved->input);
        self::assertSame($relation->outputs, $moved->outputs);
        self::assertSame($relation->columnAliases, $moved->columnAliases);
    }
}
