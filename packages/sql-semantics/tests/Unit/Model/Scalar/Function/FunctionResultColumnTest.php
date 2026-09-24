<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\FunctionResultColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(FunctionResultColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FunctionResultColumnTest extends TestCase
{
    public function testInputsAreTheProducingCall(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $column = new FunctionResultColumn($call->facts, $call->source, $call, 1, 'second');
        self::assertSame([$call], $column->inputs());
        self::assertSame(1, $column->ordinal);
        self::assertSame(ExpressionKind::Function, $column->kind);
    }

    public function testSpellingIsTheOutputName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $column = new FunctionResultColumn($call->facts, $call->source, $call, 0, 'first');
        self::assertSame('first', $column->spelling());
        self::assertSame('CUSTOM_FUNCTION', $call->spelling());
    }

    public function testWithFactsKeepsTheCallOrdinalAndName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $column = new FunctionResultColumn($call->facts, $call->source, $call, 0, 'first');
        $changed = $column->withFacts(new ExpressionFacts($column->type, Nullability::NotNull));
        self::assertNotSame($column, $changed);
        self::assertSame(Nullability::NotNull, $changed->nullability);
        self::assertSame(Nullability::Unknown, $column->nullability);
        self::assertSame($call, $changed->call);
        self::assertSame(0, $changed->ordinal);
        self::assertSame('first', $changed->name);
    }

    public function testRejectsANegativeOrdinal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new FunctionResultColumn($call->facts, $call->source, $call, -1, 'first');
    }

    public function testRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new FunctionResultColumn($call->facts, $call->source, $call, 0, '');
    }

    public function testRejectsACallFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new FunctionResultColumn($call->facts, $call->source, Expression::literal(1, Dialect::MySql), 0, 'first');
    }
}
