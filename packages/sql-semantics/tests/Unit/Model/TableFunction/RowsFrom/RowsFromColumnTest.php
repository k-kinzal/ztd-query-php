<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromColumn;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowsFromColumn::class)]
#[Medium]
final class RowsFromColumnTest extends TestCase
{
    public function testInputsNameTheProducingInvocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1)) WITH ORDINALITY');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $function = $statement->from->outputs[0]->expression;
        $ordinal = $statement->from->outputs[1]->expression;
        self::assertInstanceOf(RowsFromColumn::class, $function);
        self::assertInstanceOf(RowsFromColumn::class, $ordinal);
        self::assertSame([$statement->from->table->functions[0]->call], $function->inputs());
        self::assertSame([], $ordinal->inputs());
        self::assertSame(ExpressionKind::FunctionColumn, $ordinal->kind);
    }

    public function testSpellingIsTheColumnName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer)) AS r(b)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        self::assertSame('a', $statement->from->outputs[0]->expression->spelling());
        self::assertSame('b', $statement->from->outputs[0]->name);
    }

    public function testWithFactsKeepsTheInvocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $column = $statement->from->outputs[0]->expression;
        self::assertInstanceOf(RowsFromColumn::class, $column);
        self::assertSame(0, $column->withFacts($column->facts)->function);
    }

    public function testRejectsAnOrdinalityColumnWithoutOrdinality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $column = $statement->from->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new RowsFromColumn($column->facts, $column->source, $statement->from->table, null, 'ordinality');
    }

    public function testRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $column = $statement->from->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new RowsFromColumn($column->facts, $column->source, $statement->from->table, 0, '');
    }
}
