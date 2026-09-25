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
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(NamedArgument::class)]
#[Medium]
final class NamedArgumentTest extends TestCase
{
    public function testBothSpellingsBindTheNamedParameter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT f(1, a => 2, "B" := 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertInstanceOf(NamedArgument::class, $call->arguments[1]);
        self::assertInstanceOf(NamedArgument::class, $call->arguments[2]);
        self::assertSame(['a', 'B'], [$call->arguments[1]->name, $call->arguments[2]->name]);
        self::assertSame(ExpressionKind::NamedArgument, $call->arguments[1]->kind);
        self::assertSame('SELECT "f"(1, "a" => 2, "B" => 3)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsContainsTheValue(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $argument = new NamedArgument($value->facts, $value->source, 'days', $value);
        self::assertSame([$value], $argument->inputs());
    }

    public function testSpellingIsTheParameterName(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('days', (new NamedArgument($value->facts, $value->source, 'days', $value))->spelling());
    }

    public function testWithFactsKeepsTheNameAndValue(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $argument = new NamedArgument($value->facts, $value->source, 'days', $value);
        $copy = $argument->withFacts(new ExpressionFacts($argument->type, Nullability::MaybeNull));
        self::assertNotSame($argument, $copy);
        self::assertSame(['days', $value], [$copy->name, $copy->value]);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $argument->nullability);
    }

    public function testRejectsAnEmptyName(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new NamedArgument($value->facts, $value->source, '', $value);
    }

    public function testRejectsAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new NamedArgument($value->facts, $value->source, 'days', $value);
    }

    public function testRejectsANestedNotation(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $named = new NamedArgument($value->facts, $value->source, 'days', $value);
        $this->expectException(InvalidStructure::class);
        new NamedArgument($value->facts, $value->source, 'hours', $named);
    }
}
