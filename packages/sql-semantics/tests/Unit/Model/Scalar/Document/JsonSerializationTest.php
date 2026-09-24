<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\JsonSerialization;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonSerialization::class)]
#[Medium]
final class JsonSerializationTest extends TestCase
{
    public function testInputsListTheSerializedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d bytea NOT NULL)'));
        $query = $binder->bind('SELECT JSON_SERIALIZE(d FORMAT JSON ENCODING UTF8 RETURNING bytea FORMAT JSON ENCODING UTF8) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonSerialization::class, $value);
        self::assertSame(Format::Utf8, $value->input->format);
        self::assertSame(Format::Utf8, $value->returning?->format);
        self::assertSame([$value->input->expression], $value->inputs());
        self::assertSame('bytea', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame(ExpressionKind::JsonConversion, $value->kind);
        self::assertSame('SELECT JSON_SERIALIZE("d" FORMAT JSON ENCODING UTF8 RETURNING bytea FORMAT JSON ENCODING UTF8) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsDefaultToText(): void
    {
        $value = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('text', (new JsonSerialization($value->source, new Input($value)))->type->name);
        $this->expectException(InvalidStructure::class);
        new JsonSerialization($value->source, new Input(Expression::literal('{}', Dialect::MySql)));
    }

    public function testSpellingIdentifiesJsonSerialize(): void
    {
        $value = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('JSON_SERIALIZE', (new JsonSerialization($value->source, new Input($value)))->spelling());
    }

    public function testWithFactsPreservesTheInput(): void
    {
        $input = Expression::literal('{}', Dialect::PostgreSql);
        $value = new JsonSerialization($input->source, new Input($input, Format::Json));
        self::assertSame(Format::Json, $value->withFacts($value->facts)->input->format);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($input->facts);
    }
}
