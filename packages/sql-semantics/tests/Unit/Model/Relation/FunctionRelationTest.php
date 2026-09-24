<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\FunctionRelation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FunctionRelation::class)]
#[Medium]
final class FunctionRelationTest extends TestCase
{
    public function testExposesTheFunctionResultAsALabeledColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT g.n FROM generate_series(1,3) AS g(n)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(FunctionRelation::class, $relation);
        self::assertSame('g', $relation->alias);
        self::assertSame(['n'], $relation->columnAliases);
        self::assertSame('GENERATE_SERIES', $relation->function->spelling());
        self::assertSame(['n'], array_column($relation->outputs, 'name'));
        self::assertSame(array_column($relation->outputs, 'expression'), $relation->resultExpressions());
        self::assertSame($relation->function->type, $relation->resultExpressions()[0]->type);
        self::assertSame('SELECT "g"."n" AS "n" FROM "generate_series"(1, 3) AS "g"("n")', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testResultExpressionsFollowTheOutputs(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT g.n FROM generate_series(1,3) AS g(n)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(FunctionRelation::class, $relation);
        $expressions = $relation->resultExpressions();
        self::assertCount(1, $expressions);
        self::assertSame($relation->outputs[0]->expression, $expressions[0]);
        self::assertSame($relation->function->type, $expressions[0]->type);
    }

    public function testWithScopeRetainsTheFunctionAndItsOutputs(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT g FROM generate_series(1,3) AS g');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(FunctionRelation::class, $relation);
        $moved = $relation->withScope('s9');
        self::assertNotSame($relation, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertSame($query->scopeId, $relation->scopeId);
        self::assertSame($relation->function, $moved->function);
        self::assertSame($relation->outputs, $moved->outputs);
        self::assertSame(['g'], $moved->columnAliases);
        self::assertSame(['g'], array_column($moved->outputs, 'name'));
    }
}
