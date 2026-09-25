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
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\Construction\JsonMember;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectAggregate;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonObjectAggregate::class)]
#[Medium]
final class JsonObjectAggregateTest extends TestCase
{
    public function testInputsListTheMemberAndFilter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_OBJECTAGG(k VALUE v WITH UNIQUE KEYS RETURNING jsonb) FILTER (WHERE v > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectAggregate::class, $value);
        self::assertSame(JsonNullHandling::Null, $value->onNull);
        self::assertTrue($value->uniqueKeys);
        self::assertNotNull($value->filter);
        self::assertSame([$value->member->key, $value->member->value->expression, $value->filter], $value->inputs());
        self::assertSame(ExpressionKind::Aggregate, $value->kind);
        self::assertSame('jsonb', $value->type->name);
        self::assertSame('SELECT JSON_OBJECTAGG("k" : "v" WITH UNIQUE KEYS RETURNING jsonb) FILTER (WHERE ("v" > 0)) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $key = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonObjectAggregate($key->source, new JsonMember($key, new Input($key)));
    }

    public function testSpellingIdentifiesJsonObjectAgg(): void
    {
        $key = Expression::literal('a', Dialect::PostgreSql);
        self::assertSame('JSON_OBJECTAGG', (new JsonObjectAggregate($key->source, new JsonMember($key, new Input($key))))->spelling());
    }

    public function testWithFactsPreservesTheMember(): void
    {
        $key = Expression::literal('a', Dialect::PostgreSql);
        $value = new JsonObjectAggregate($key->source, new JsonMember($key, new Input($key)), JsonNullHandling::Absent);
        self::assertSame(JsonNullHandling::Absent, $value->withFacts($value->facts)->onNull);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($key->facts);
    }
}
