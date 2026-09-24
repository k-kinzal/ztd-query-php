<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function\Argument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\Argument\NamedArgument;
use SqlSemantics\Model\Scalar\Function\Argument\VariadicArgument;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(VariadicArgument::class)]
#[Medium]
final class VariadicArgumentTest extends TestCase
{
    public function testVariadicMarksThePositionalOrNamedLastArgument(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $positional = $binder->bind('SELECT f(1, VARIADIC ARRAY[2, 3])');
        $named = $binder->bind('SELECT f(VARIADIC items => ARRAY[2])');
        self::assertInstanceOf(BoundSelect::class, $positional);
        self::assertInstanceOf(BoundSelect::class, $named);
        $call = $positional->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertInstanceOf(VariadicArgument::class, $call->arguments[1]);
        self::assertSame(ExpressionKind::VariadicArgument, $call->arguments[1]->kind);
        $namedCall = $named->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $namedCall);
        self::assertInstanceOf(VariadicArgument::class, $namedCall->arguments[0]);
        self::assertInstanceOf(NamedArgument::class, $namedCall->arguments[0]->value);
        self::assertSame('SELECT "f"(1, VARIADIC ARRAY[2, 3])', $positional->toString());
        self::assertSame('SELECT "f"(VARIADIC "items" => ARRAY[2])', $named->toString());
        self::assertSame($named->toString(), $binder->bind($named->toString())->toString());
    }

    public function testInputsContainsTheValue(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame([$value], (new VariadicArgument($value->facts, $value->source, $value))->inputs());
    }

    public function testSpellingIsTheVariadicKeyword(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('VARIADIC', (new VariadicArgument($value->facts, $value->source, $value))->spelling());
    }

    public function testWithFactsKeepsTheValue(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $argument = new VariadicArgument($value->facts, $value->source, $value);
        $copy = $argument->withFacts(new ExpressionFacts($argument->type, Nullability::MaybeNull));
        self::assertNotSame($argument, $copy);
        self::assertSame($value, $copy->value);
        self::assertSame(Nullability::NotNull, $argument->nullability);
    }

    public function testRejectsAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new VariadicArgument($value->facts, $value->source, $value);
    }

    public function testRejectsARepeatedMark(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $variadic = new VariadicArgument($value->facts, $value->source, $value);
        $this->expectException(InvalidStructure::class);
        new VariadicArgument($value->facts, $value->source, $variadic);
    }
}
