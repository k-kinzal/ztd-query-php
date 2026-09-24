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
use SqlSemantics\Model\Relation\CteReference;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CteReference::class)]
#[Medium]
final class CteReferenceTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testExposesTheDefinitionOutputsAtTheReferenceSite(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind('WITH c AS (SELECT 1 AS n) SELECT d.n FROM c AS d');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(CteReference::class, $relation);
        self::assertSame('c', $relation->name);
        self::assertSame('d', $relation->alias);
        self::assertSame($relation->definition->name, $relation->name);
        self::assertSame(array_column($relation->definition->query->resultColumns(), 'expression'), $relation->resultExpressions());
        self::assertSame(['n'], array_column($relation->declaration->columns, 'name'));
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testResultExpressionsComeFromTheDefinitionQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c AS (SELECT 1 AS n, 2 AS s) SELECT s FROM c');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(CteReference::class, $relation);
        $expressions = $relation->resultExpressions();
        self::assertCount(2, $expressions);
        self::assertSame(['1', '2'], array_map(static fn ($expression): ?string => $expression->spelling(), $expressions));
        self::assertSame(array_column($relation->definition->query->resultColumns(), 'expression'), $expressions);
        self::assertSame($expressions, $relation->withScope('s9')->resultExpressions());
    }

    public function testWithScopeRetainsTheDefinition(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c AS (SELECT 1 AS n) SELECT n FROM c');
        self::assertInstanceOf(BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(CteReference::class, $relation);
        $moved = $relation->withScope('s9');
        self::assertNotSame($relation, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertSame($query->scopeId, $relation->scopeId);
        self::assertSame($relation->definition, $moved->definition);
        self::assertNull($moved->alias);
    }
}
