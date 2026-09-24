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
use SqlSemantics\Model\Scalar\Function\OrdinalityColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(OrdinalityColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OrdinalityColumnTest extends TestCase
{
    public function testInputsAreTheProducingCall(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'), Nullability::NotNull);
        $column = new OrdinalityColumn($facts, $call->source, $call);
        self::assertSame([$call], $column->inputs());
        self::assertSame('bigint', $column->type->name);
        self::assertSame(ExpressionKind::Function, $column->kind);
    }

    public function testSpellingIsOrdinality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $column = new OrdinalityColumn($call->facts, $call->source, $call);
        self::assertSame('ORDINALITY', $column->spelling());
    }

    public function testWithFactsKeepsTheCall(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $column = new OrdinalityColumn($call->facts, $call->source, $call);
        $changed = $column->withFacts(new ExpressionFacts($column->type, Nullability::NotNull));
        self::assertNotSame($column, $changed);
        self::assertSame(Nullability::NotNull, $changed->nullability);
        self::assertSame(Nullability::Unknown, $column->nullability);
        self::assertSame($call, $changed->call);
    }

    public function testRejectsACallFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        $this->expectException(InvalidStructure::class);
        new OrdinalityColumn($call->facts, $call->source, Expression::literal(1, Dialect::Sqlite));
    }
}
