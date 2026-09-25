<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonArrayConstructor::class)]
#[Medium]
final class JsonArrayConstructorTest extends TestCase
{
    public function testInputsListTheElementsInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d text, v integer)'));
        $query = $binder->bind('SELECT JSON_ARRAY(v, d FORMAT JSON NULL ON NULL RETURNING jsonb) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonArrayConstructor::class, $value);
        self::assertSame(JsonNullHandling::Null, $value->onNull);
        self::assertSame(Format::Json, $value->elements[1]->format);
        self::assertSame([$value->elements[0]->expression, $value->elements[1]->expression], $value->inputs());
        self::assertSame('jsonb', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame('SELECT JSON_ARRAY("v", "d" FORMAT JSON NULL ON NULL RETURNING jsonb) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectANullClauseWithoutElements(): void
    {
        $source = Expression::literal(1, Dialect::PostgreSql)->source;
        self::assertSame([], (new JsonArrayConstructor($source, []))->inputs());
        $this->expectException(InvalidStructure::class);
        new JsonArrayConstructor($source, [], JsonNullHandling::Null);
    }

    public function testInputsRejectAnElementOfAnotherDialect(): void
    {
        $element = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonArrayConstructor($element->source, [new Input($element)]);
    }

    public function testSpellingIdentifiesJsonArray(): void
    {
        self::assertSame('JSON_ARRAY', (new JsonArrayConstructor(Expression::literal(1, Dialect::PostgreSql)->source, []))->spelling());
    }

    public function testWithFactsPreservesTheElements(): void
    {
        $element = Expression::literal(1, Dialect::PostgreSql);
        $value = new JsonArrayConstructor($element->source, [new Input($element)], JsonNullHandling::Null);
        self::assertSame(JsonNullHandling::Null, $value->withFacts($value->facts)->onNull);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($element->facts);
    }
}
