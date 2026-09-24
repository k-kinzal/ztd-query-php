<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\RowsFrom\RowsFromRelations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RowsFromRelations::class)]
#[Medium]
final class RowsFromRelationsTest extends TestCase
{
    public function testRelationAppliesTheAliasAndColumnAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer)) WITH ORDINALITY AS r(x)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        self::assertSame('r', $statement->from->alias);
        self::assertSame(['x', 'ordinality'], array_map(static fn (OutputColumn $column): ?string => $column->name, $statement->from->outputs));
    }

    public function testOutputsPadSeveralInvocationsWithNulls(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(), g() AS (z integer)) WITH ORDINALITY AS x(a, b, c, d)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $relation = $statement->from;
        $outputs = RowsFromRelations::outputs($relation->source, $relation->table, $relation->columnAliases);
        self::assertSame(['a', 'b', 'c', 'd'], array_map(static fn (OutputColumn $column): ?string => $column->name, $outputs));
        self::assertSame([Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::NotNull], array_map(static fn (OutputColumn $column): Nullability => $column->expression->nullability, $outputs));
    }

    public function testColumnsNameSingleResultsAfterTheFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(), g() AS (z integer))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $columns = RowsFromRelations::columns($statement->from->table);
        self::assertSame(['f', 'z'], [$columns[0][0][0], $columns[1][0][0]]);
    }
}
