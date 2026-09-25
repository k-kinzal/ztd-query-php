<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\SliceAccess;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(SliceAccess::class)]
#[Medium]
final class SliceAccessTest extends TestCase
{
    public function testInputsOrdersTheBaseAndBothBounds(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(tags INTEGER[])'));
        $statement = $binder->bind('SELECT tags[1:2] FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $slice = $statement->outputs[0]->expression;
        self::assertInstanceOf(SliceAccess::class, $slice);
        self::assertNotNull($slice->lower);
        self::assertNotNull($slice->upper);
        self::assertSame('1', $slice->lower->spelling());
        self::assertSame('2', $slice->upper->spelling());
        self::assertSame([$slice->base, $slice->lower, $slice->upper], $slice->inputs());
        self::assertSame('integer[]', $slice->type->name);
        self::assertSame('SELECT "tags"[1 : 2] FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('SELECT "tags"[1 : 2] FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsOmitsAnOpenBound(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(tags INTEGER[])')))->bind('SELECT tags[:2] FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $slice = $statement->outputs[0]->expression;
        self::assertInstanceOf(SliceAccess::class, $slice);
        self::assertNull($slice->lower);
        self::assertNotNull($slice->upper);
        self::assertSame([$slice->base, $slice->upper], $slice->inputs());
        self::assertSame('SELECT "tags"[: 2] FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testSpellingIsTheSliceBrackets(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $upper = Expression::literal(2, Dialect::PostgreSql);
        $slice = new SliceAccess($base->facts, $base->source, $base, null, $upper);
        self::assertSame('[:]', $slice->spelling());
        self::assertSame('"tags"[: 2]', $slice->structure()->toString());
    }

    public function testRejectsABoundFromAnotherDialect(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new SliceAccess($base->facts, $base->source, $base, Expression::literal(1, Dialect::MySql), null);
    }

    public function testWithFactsKeepsTheBaseAndBounds(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $lower = Expression::literal(1, Dialect::PostgreSql);
        $slice = new SliceAccess($base->facts, $base->source, $base, $lower, null);
        $copy = $slice->withFacts(new ExpressionFacts($slice->type, Nullability::MaybeNull));
        self::assertNotSame($slice, $copy);
        self::assertSame($base, $copy->base);
        self::assertSame($lower, $copy->lower);
        self::assertNull($copy->upper);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $slice->nullability);
    }
}
