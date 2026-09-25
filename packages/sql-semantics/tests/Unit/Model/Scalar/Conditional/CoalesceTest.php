<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\Coalesce;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Coalesce::class)]
#[Medium]
final class CoalesceTest extends TestCase
{
    public function testInputsRetainsTheOrderedAlternatives(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT COALESCE(NULL, 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Coalesce::class, $value);
        self::assertCount(2, $value->arguments);
        self::assertSame($value->arguments, $value->inputs());
        self::assertSame('1', $value->arguments[1]->spelling());
        self::assertSame('integer', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame('SELECT COALESCE(NULL, 1)', $statement->toString());
        self::assertSame('SELECT COALESCE(NULL, 1)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsRequiresAtLeastOneArgument(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new Coalesce($origin->facts, $origin->source, []);
    }

    public function testSpellingIsTheFunctionKeyword(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $value = new Coalesce($first->facts, $first->source, [$first, Expression::literal(2, Dialect::PostgreSql)]);
        self::assertSame('COALESCE', $value->spelling());
        self::assertSame('COALESCE(1, 2)', $value->structure()->toString());
    }

    public function testWithFactsKeepsTheArguments(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $second = Expression::literal(2, Dialect::PostgreSql);
        $value = new Coalesce($first->facts, $first->source, [$first, $second]);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame([$first, $second], $copy->arguments);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }
}
