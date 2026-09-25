<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FieldAccessTest extends TestCase
{
    public function testPreservesTheInputRowAndFieldName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (ROW(1,2)).f1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class, $value);
        self::assertSame('f1', $value->field);
        self::assertSame('row', $value->base->kind->value);
    }

    public function testInputsContainsOnlyTheBaseRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (ROW(1,2)).f1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class, $value);
        self::assertSame([$value->base], $value->inputs());
        self::assertSame('SELECT (ROW(1, 2))."f1"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame('SELECT (ROW(1, 2))."f1"', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingPrefixesTheFieldWithADot(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (ROW(1,2)).f1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class, $value);
        self::assertSame('.f1', $value->spelling());
    }

    public function testWithFactsKeepsTheBaseAndFieldName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (ROW(1,2)).f1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class, $value);
        $copy = $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($value->type, \SqlSemantics\Type\Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame($value->base, $copy->base);
        self::assertSame('f1', $copy->field);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $copy->nullability);
        self::assertNotSame(\SqlSemantics\Type\Nullability::MaybeNull, $value->nullability);
    }
}
