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
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Query\Ordering\OutputPosition;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonArrayAggregate::class)]
#[Medium]
final class JsonArrayAggregateTest extends TestCase
{
    public function testInputsListTheElementOrderingAndFilter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_ARRAYAGG(v ORDER BY k DESC NULLS LAST NULL ON NULL RETURNING jsonb) FILTER (WHERE v > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonArrayAggregate::class, $value);
        self::assertSame(JsonNullHandling::Null, $value->onNull);
        self::assertFalse($value->orderBy[0]->nullsFirst);
        self::assertSame([$value->element->expression, $value->orderBy[0]->key, $value->filter], $value->inputs());
        self::assertSame('jsonb', $value->type->name);
        self::assertSame('SELECT JSON_ARRAYAGG("v" ORDER BY "k" DESC NULLS LAST NULL ON NULL RETURNING jsonb) FILTER (WHERE ("v" > 0)) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsBecomeAWindowCallWithOver(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_ARRAYAGG(v) OVER (PARTITION BY k) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $window = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        self::assertInstanceOf(JsonArrayAggregate::class, $window->function);
        self::assertSame('SELECT JSON_ARRAYAGG("v") OVER (PARTITION BY "k") FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnOutputPositionOrdering(): void
    {
        $element = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new JsonArrayAggregate($element->source, new Input($element), [new Ordering(new OutputPosition(new \SqlSemantics\Model\OutputColumn(0, 'a', $element)))]);
    }

    public function testSpellingIdentifiesJsonArrayAgg(): void
    {
        $element = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('JSON_ARRAYAGG', (new JsonArrayAggregate($element->source, new Input($element)))->spelling());
    }

    public function testWithFactsPreservesTheElement(): void
    {
        $element = Expression::literal(1, Dialect::PostgreSql);
        $value = new JsonArrayAggregate($element->source, new Input($element), [new Ordering($element, true)]);
        self::assertTrue($value->withFacts($value->facts)->orderBy[0]->descending);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($element->facts);
    }
}
