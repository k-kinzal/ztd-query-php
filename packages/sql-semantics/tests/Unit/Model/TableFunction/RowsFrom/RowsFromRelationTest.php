<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowsFromRelation::class)]
#[Medium]
final class RowsFromRelationTest extends TestCase
{
    public function testResultExpressionsFollowTheOutputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(), g()) WITH ORDINALITY AS r');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        self::assertCount(3, $statement->from->resultExpressions());
        self::assertSame('r', $statement->from->alias);
    }

    public function testWithScopeKeepsTheTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f()) AS r(a)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $copy = $statement->from->withScope('other');
        self::assertSame('other', $copy->scopeId);
        self::assertSame($statement->from->table, $copy->table);
        self::assertSame(['a'], $copy->columnAliases);
    }

    public function testRejectsMoreAliasesThanColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f()) AS r(a)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(RowsFromRelation::class, $relation);
        $this->expectException(InvalidStructure::class);
        new RowsFromRelation($relation->id, $relation->scopeId, $relation->declaration, $relation->alias, $relation->source, $relation->table, $relation->outputs, ['a', 'b']);
    }
}
