<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\Position;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Position::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class PositionTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'integer'])]
    #[TestWith([Dialect::MySql, 'bigint'])]
    public function testInputsDistinguishesTheNeedleAndHaystack(Dialect $dialect, string $type): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind("SELECT POSITION('a' IN 'cat')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $position = $query->outputs[0]->expression;
        self::assertInstanceOf(Position::class, $position);
        self::assertSame("'a'", $position->needle->spelling());
        self::assertSame("'cat'", $position->haystack->spelling());
        self::assertSame([$position->needle, $position->haystack], $position->inputs());
        self::assertSame($type, $position->type->name);
        self::assertSame(Nullability::NotNull, $position->nullability);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectsMixedDialects(): void
    {
        $needle = Expression::literal('a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new Position($needle->source, $needle, Expression::literal('cat', Dialect::MySql));
    }

    public function testInputsRejectsSqlite(): void
    {
        $value = Expression::literal('a', Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new Position($value->source, $value, $value);
    }

    public function testWithFactsPreservesSearchOperands(): void
    {
        $needle = Expression::literal('a', Dialect::PostgreSql);
        $haystack = Expression::literal('cat', Dialect::PostgreSql);
        $position = new Position($needle->source, $needle, $haystack);
        $copy = $position->withFacts($position->facts);
        self::assertNotSame($position, $copy);
        self::assertSame($needle, $copy->needle);
        self::assertSame($haystack, $copy->haystack);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $needle = Expression::literal('a', Dialect::PostgreSql);
        $position = new Position($needle->source, $needle, $needle);
        $this->expectException(InvalidStructure::class);
        $position->withFacts($needle->facts);
    }

    public function testSpellingIdentifiesASearch(): void
    {
        $value = Expression::literal(null, Dialect::PostgreSql);
        $position = new Position($value->source, $value, $value);
        self::assertSame('POSITION', $position->spelling());
        self::assertSame(Nullability::AlwaysNull, $position->nullability);
    }

    public function testInputsAreReboundAfterAnImmutableChange(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT POSITION('a' IN 'cat')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $position = $query->outputs[0]->expression;
        self::assertInstanceOf(Position::class, $position);
        $copy = $query->replaceExpression($position->needle, Expression::literal(null, Dialect::PostgreSql));
        self::assertSame("SELECT POSITION(NULL IN 'cat')", $copy->toString());
        self::assertSame(Nullability::AlwaysNull, $copy->outputs[0]->expression->nullability);
        self::assertSame(Nullability::NotNull, $position->nullability);
    }
}
