<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Value\ArrayConstructor;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ArrayConstructor::class)]
#[Medium]
final class ArrayConstructorTest extends TestCase
{
    public function testInputsListTheElementsInWrittenOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ARRAY[1, 2], ARRAY[[1], [2]], ARRAY[]::text[]');
        self::assertInstanceOf(BoundSelect::class, $query);
        $flat = $query->outputs[0]->expression;
        self::assertInstanceOf(ArrayConstructor::class, $flat);
        self::assertSame(['1', '2'], array_map(static fn (Expression $item): ?string => $item->spelling(), $flat->inputs()));
        self::assertSame('integer[]', $flat->type->name);
        self::assertSame(Nullability::NotNull, $flat->nullability);
        self::assertSame(ExpressionKind::ArrayConstructor, $flat->kind);
        $nested = $query->outputs[1]->expression;
        self::assertInstanceOf(ArrayConstructor::class, $nested);
        self::assertContainsOnlyInstancesOf(ArrayConstructor::class, $nested->elements);
        self::assertSame('integer[]', $nested->type->name);
        self::assertSame('text[]', $query->outputs[2]->expression->type->name);
        self::assertSame('SELECT ARRAY[1, 2], ARRAY[ARRAY[1], ARRAY[2]], CAST(ARRAY[] AS text [])', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testArrayOfWrapsAScalarAndKeepsAnArrayOrUnknownType(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $array = ArrayConstructor::arrayOf($integer);
        self::assertInstanceOf(ArrayStorage::class, $array->identity);
        self::assertSame('integer[]', $array->name);
        self::assertSame($array, ArrayConstructor::arrayOf($array));
        self::assertSame('unknown', ArrayConstructor::arrayOf(TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown'))->name);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new ArrayConstructor($value->source, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), [$value]);
    }

    public function testSpellingNamesTheConstructor(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('ARRAY', (new ArrayConstructor($value->source, $value->type, [$value]))->spelling());
    }

    public function testWithFactsPreservesTheElements(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $array = new ArrayConstructor($value->source, $value->type, [$value]);
        $copy = $array->withFacts($array->facts);
        self::assertNotSame($array, $copy);
        self::assertSame([$value], $copy->elements);
        $this->expectException(InvalidStructure::class);
        $array->withFacts($value->facts);
    }
}
