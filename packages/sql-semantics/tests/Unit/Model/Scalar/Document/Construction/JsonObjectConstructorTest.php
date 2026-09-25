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
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonObjectConstructor::class)]
#[Medium]
final class JsonObjectConstructorTest extends TestCase
{
    public function testInputsListEachKeyAndValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)'));
        $query = $binder->bind("SELECT JSON_OBJECT(k VALUE v, 'b': 2 ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectConstructor::class, $value);
        self::assertSame(JsonNullHandling::Absent, $value->onNull);
        self::assertTrue($value->uniqueKeys);
        self::assertSame([$value->members[0]->key, $value->members[0]->value->expression, $value->members[1]->key, $value->members[1]->value->expression], $value->inputs());
        self::assertSame('jsonb', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame(ExpressionKind::JsonConstructor, $value->kind);
        self::assertSame('SELECT JSON_OBJECT("k" : "v", \'b\' : 2 ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectOptionsWithoutMembers(): void
    {
        $source = Expression::literal(1, Dialect::PostgreSql)->source;
        self::assertSame('json', (new JsonObjectConstructor($source, []))->type->name);
        $this->expectException(InvalidStructure::class);
        new JsonObjectConstructor($source, [], JsonNullHandling::Absent);
    }

    public function testInputsRejectAMemberOfAnotherDialect(): void
    {
        $key = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonObjectConstructor($key->source, [new JsonMember($key, new Input(Expression::literal(1, Dialect::PostgreSql)))]);
    }

    public function testSpellingIdentifiesJsonObject(): void
    {
        self::assertSame('JSON_OBJECT', (new JsonObjectConstructor(Expression::literal(1, Dialect::PostgreSql)->source, []))->spelling());
    }

    public function testWithFactsPreservesTheMembers(): void
    {
        $key = Expression::literal('a', Dialect::PostgreSql);
        $value = new JsonObjectConstructor($key->source, [new JsonMember($key, new Input($key))], JsonNullHandling::Null, true);
        $copy = $value->withFacts($value->facts);
        self::assertSame($value->members, $copy->members);
        self::assertTrue($copy->uniqueKeys);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($key->facts);
    }
}
