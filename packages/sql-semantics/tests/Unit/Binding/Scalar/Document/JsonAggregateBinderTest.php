<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\JsonAggregateBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectAggregate;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonAggregateBinder::class)]
#[Medium]
final class JsonAggregateBinderTest extends TestCase
{
    public function testBindWrapsAnAggregateWithOverInAWindowCall(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_OBJECTAGG(k : v) FILTER (WHERE v IS NOT NULL) OVER w FROM t WINDOW w AS (ORDER BY k)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $window = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        self::assertInstanceOf(JsonObjectAggregate::class, $window->function);
        self::assertNotNull($window->function->filter);
        self::assertSame('SELECT JSON_OBJECTAGG("k" : "v") FILTER (WHERE ("v" IS NOT NULL)) OVER "w" FROM "public"."t" WINDOW "w" AS (ORDER BY "k" ASC)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testObjectKeepsTheMemberAndOptions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_OBJECTAGG(k VALUE v ABSENT ON NULL WITHOUT UNIQUE RETURNING text) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectAggregate::class, $value);
        self::assertSame(JsonNullHandling::Absent, $value->onNull);
        self::assertFalse($value->uniqueKeys);
        self::assertSame('text', $value->type->name);
        self::assertSame('SELECT JSON_OBJECTAGG("k" : "v" ABSENT ON NULL RETURNING text) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testArrayKeepsTheElementOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind('SELECT JSON_ARRAYAGG(k FORMAT JSON ORDER BY v, k DESC) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonArrayAggregate::class, $value);
        self::assertCount(2, $value->orderBy);
        self::assertSame(JsonNullHandling::Absent, $value->onNull);
        self::assertSame('SELECT JSON_ARRAYAGG("k" FORMAT JSON ORDER BY "v" ASC, "k" DESC) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT json_objectagg(a : b) FROM t', 'SELECT JSON_OBJECTAGG("a" : "b") FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT json_arrayagg(b) FROM t', 'SELECT JSON_ARRAYAGG("b") FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT json_objectagg(a VALUE b) FILTER (WHERE b > 0) FROM t', 'SELECT JSON_OBJECTAGG("a" : "b") FILTER (WHERE ("b" > 0)) FROM "public"."t"'])]
    public function testBindReadsLowercaseAggregates(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a text, b int)')))->bind($sql)));
    }

    public function testBindRejectsANonBooleanFilter(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a text, b int)')))->bind('SELECT json_objectagg(a : b) FILTER (WHERE b) FROM t');
    }
}
