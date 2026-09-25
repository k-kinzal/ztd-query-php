<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\RowsFrom\RowsFromRelations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromColumn;
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

    #[TestWith(['SELECT * FROM ROWS FROM (Upper($1)) x', 'SELECT "x"."upper" AS "upper" FROM ROWS FROM("upper"($1)) AS "x"'])]
    #[TestWith(['SELECT * FROM ROWS FROM (Upper($1)) AS x', 'SELECT "x"."upper" AS "upper" FROM ROWS FROM("upper"($1)) AS "x"'])]
    #[TestWith(['SELECT * FROM ROWS FROM (Upper($1)) WITH ORDINALITY', 'SELECT "upper"."upper" AS "upper", "upper"."ordinality" AS "ordinality" FROM ROWS FROM("upper"($1)) WITH ORDINALITY'])]
    #[TestWith(['SELECT * FROM ROWS FROM (Upper($1)) WITH ORDINALITY AS x(a, b)', 'SELECT "x"."a" AS "a", "x"."b" AS "b" FROM ROWS FROM("upper"($1)) WITH ORDINALITY AS "x"("a", "b")'])]
    #[TestWith(['SELECT * FROM ROWS FROM (json_to_record($1) AS (a int)) AS x(p)', 'SELECT "x"."p" AS "p" FROM ROWS FROM("json_to_record"($1) AS ("a" integer)) AS "x"("p")'])]
    public function testRelationNamesTheRelationAfterItsAliasOrFirstFunction(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }

    public function testOutputsNameFurtherColumnsOfTheLastOpenInvocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (Upper($1), json_to_record($2) AS (a int)) AS x(p, q, r)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $outputs = $statement->from->outputs;
        self::assertInstanceOf(RowsFromColumn::class, $outputs[0]->expression);
        self::assertInstanceOf(RowsFromColumn::class, $outputs[1]->expression);
        self::assertInstanceOf(RowsFromColumn::class, $outputs[2]->expression);
        self::assertSame(['upper', 'upper1', 'a'], [$outputs[0]->expression->name, $outputs[1]->expression->name, $outputs[2]->expression->name]);
        self::assertSame([0, 0, 1], [$outputs[0]->expression->function, $outputs[1]->expression->function, $outputs[2]->expression->function]);
    }

    public function testOutputsKeepTheNullabilityOfASingleInvocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (Upper($1)) AS x(p, q, r)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $outputs = $statement->from->outputs;
        self::assertInstanceOf(RowsFromColumn::class, $outputs[2]->expression);
        self::assertSame('upper2', $outputs[2]->expression->name);
        self::assertSame([Nullability::Unknown, Nullability::Unknown, Nullability::Unknown], array_map(static fn (OutputColumn $column): Nullability => $column->expression->nullability, $outputs));
    }
}
