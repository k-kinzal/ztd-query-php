<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayQuery;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonArrayQuery::class)]
#[Medium]
final class JsonArrayQueryTest extends TestCase
{
    public function testInputsListTheQueryColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d text)'));
        $query = $binder->bind('SELECT JSON_ARRAY(SELECT d FROM t ORDER BY d FORMAT JSON RETURNING jsonb)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonArrayQuery::class, $value);
        self::assertSame(Format::Json, $value->format);
        self::assertSame([$value->query->resultColumns()[0]->expression], $value->inputs());
        self::assertSame('jsonb', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame('SELECT JSON_ARRAY(SELECT "d" AS "d" FROM "public"."t" ORDER BY "d" ASC FORMAT JSON RETURNING jsonb)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectAQueryOfTwoColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new JsonArrayQuery($query->source, $query);
    }

    public function testSpellingIdentifiesJsonArray(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        self::assertSame('JSON_ARRAY', (new JsonArrayQuery($query->source, $query))->spelling());
    }

    public function testWithFactsPreservesTheQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $value = new JsonArrayQuery($query->source, $query, Format::Json);
        self::assertSame($query, $value->withFacts($value->facts)->query);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($query->resultColumns()[0]->expression->facts);
    }
}
