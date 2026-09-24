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
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\ElementAccess;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ElementAccess::class)]
#[Medium]
final class ElementAccessTest extends TestCase
{
    public function testInputsOrdersTheBaseBeforeTheIndex(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(tags INTEGER[])'));
        $statement = $binder->bind('SELECT tags[1] FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $access = $statement->outputs[0]->expression;
        self::assertInstanceOf(ElementAccess::class, $access);
        self::assertInstanceOf(ColumnReference::class, $access->base);
        self::assertSame('tags', $access->base->binding->column->name);
        self::assertSame('1', $access->index->spelling());
        self::assertSame([$access->base, $access->index], $access->inputs());
        self::assertSame('integer', $access->type->name);
        self::assertSame(Nullability::MaybeNull, $access->nullability);
        self::assertSame('SELECT "tags"[1] FROM "public"."t"', $statement->toString());
        self::assertSame('SELECT "tags"[1] FROM "public"."t"', $binder->bind($statement->toString())->toString());
    }

    public function testSpellingIsTheSubscriptBrackets(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $index = Expression::literal(1, Dialect::PostgreSql);
        $access = new ElementAccess($index->facts, $index->source, $base, $index);
        self::assertSame('[]', $access->spelling());
        self::assertSame('"tags"[1]', $access->structure()->toString());
    }

    public function testRejectsAnIndexFromAnotherDialect(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $index = Expression::literal(1, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new ElementAccess($base->facts, $base->source, $base, $index);
    }

    public function testWithFactsKeepsTheBaseAndIndex(): void
    {
        $base = Expression::reference(['tags'], Dialect::PostgreSql);
        $index = Expression::literal(1, Dialect::PostgreSql);
        $access = new ElementAccess($index->facts, $index->source, $base, $index);
        $copy = $access->withFacts(new ExpressionFacts($access->type, Nullability::MaybeNull));
        self::assertNotSame($access, $copy);
        self::assertSame($base, $copy->base);
        self::assertSame($index, $copy->index);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $access->nullability);
    }
}
