<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DerivedRelation;
use SqlSemantics\Model\Relation\Joining\CrossJoin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DerivedRelation::class)]
#[Medium]
final class DerivedRelationTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testExposesTheSubqueryOutputsUnderTheAlias(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind('SELECT d.n FROM (SELECT 1 AS n) AS d');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(DerivedRelation::class, $relation);
        self::assertSame('d', $relation->alias);
        self::assertSame([], $relation->columnAliases);
        self::assertFalse($relation->lateral);
        self::assertSame(array_column($relation->query->resultColumns(), 'expression'), $relation->resultExpressions());
        self::assertSame(['n'], array_column($relation->declaration->columns, 'name'));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testRenamesTheOutputsWithColumnAliases(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT d.m FROM (SELECT 1 AS n) AS d(m)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(DerivedRelation::class, $relation);
        self::assertSame(['m'], $relation->columnAliases);
        self::assertSame(['m'], array_column($relation->declaration->columns, 'name'));
        self::assertSame('SELECT "d"."m" AS "m" FROM(SELECT 1 AS "n") AS "d"("m")', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testResultExpressionsComeFromTheSubquery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT d.m FROM (SELECT 1 AS n, 2 AS s) AS d(m, o)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(DerivedRelation::class, $relation);
        $expressions = $relation->resultExpressions();
        self::assertCount(2, $expressions);
        self::assertSame(['1', '2'], array_map(static fn ($expression): ?string => $expression->spelling(), $expressions));
        self::assertSame(array_column($relation->query->resultColumns(), 'expression'), $expressions);
        self::assertSame(['m', 'o'], array_column($relation->declaration->columns, 'name'));
    }

    public function testWithScopeRetainsTheLateralSubquery(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT l.n FROM t, LATERAL (SELECT t.id AS n) AS l');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(CrossJoin::class, $query->from);
        $relation = $query->from->right;
        self::assertInstanceOf(DerivedRelation::class, $relation);
        self::assertTrue($relation->lateral);
        $moved = $relation->withScope('s9');
        self::assertNotSame($relation, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertTrue($moved->lateral);
        self::assertSame($relation->query, $moved->query);
        self::assertSame($relation->id, $moved->id);
    }
}
