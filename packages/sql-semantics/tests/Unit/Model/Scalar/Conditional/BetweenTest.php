<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\Between;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Between::class)]
#[Medium]
final class BetweenTest extends TestCase
{
    public function testInputsOrdersTheValueBeforeItsBounds(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 NOT BETWEEN SYMMETRIC 0 AND 2');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $range = $statement->outputs[0]->expression;
        self::assertInstanceOf(Between::class, $range);
        self::assertSame('1', $range->value->spelling());
        self::assertSame('0', $range->lower->spelling());
        self::assertSame('2', $range->upper->spelling());
        self::assertSame([$range->value, $range->lower, $range->upper], $range->inputs());
        self::assertTrue($range->negated);
        self::assertTrue($range->symmetric);
        self::assertSame('SELECT (1 NOT BETWEEN SYMMETRIC 0 AND 2)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('SELECT (1 NOT BETWEEN SYMMETRIC 0 AND 2)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([false, 'BETWEEN'])]
    #[TestWith([true, 'NOT BETWEEN'])]
    public function testSpellingDistinguishesNegation(bool $negated, string $spelling): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $range = new Between($value->facts, $value->source, $value, Expression::literal(0, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql), $negated, false);
        self::assertSame($spelling, $range->spelling());
        self::assertSame('(1 ' . $spelling . ' 0 AND 2)', $range->structure()->toString());
    }

    public function testRejectsBoundsFromAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new Between($value->facts, $value->source, $value, Expression::literal(0, Dialect::Sqlite), Expression::literal(2, Dialect::PostgreSql), false, false);
    }

    public function testWithFactsKeepsTheOperandsAndFlags(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $lower = Expression::literal(0, Dialect::PostgreSql);
        $upper = Expression::literal(2, Dialect::PostgreSql);
        $range = new Between($value->facts, $value->source, $value, $lower, $upper, true, true);
        $copy = $range->withFacts(new ExpressionFacts($range->type, Nullability::MaybeNull));
        self::assertNotSame($range, $copy);
        self::assertSame([$value, $lower, $upper], $copy->inputs());
        self::assertTrue($copy->negated);
        self::assertTrue($copy->symmetric);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $range->nullability);
    }
}
