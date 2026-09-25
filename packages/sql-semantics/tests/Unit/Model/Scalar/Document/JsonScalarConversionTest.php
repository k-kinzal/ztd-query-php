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
use SqlSemantics\Model\Scalar\Document\JsonScalarConversion;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonScalarConversion::class)]
#[Medium]
final class JsonScalarConversionTest extends TestCase
{
    public function testInputsListTheConvertedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n integer NOT NULL)'));
        $query = $binder->bind('SELECT JSON_SCALAR(n + 1) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonScalarConversion::class, $value);
        self::assertSame([$value->value], $value->inputs());
        self::assertSame('json', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame('SELECT JSON_SCALAR(("n" + 1)) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new JsonScalarConversion($value->source, $value);
    }

    public function testSpellingIdentifiesJsonScalar(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('JSON_SCALAR', (new JsonScalarConversion($value->source, $value))->spelling());
    }

    public function testWithFactsPreservesTheValue(): void
    {
        $input = Expression::literal(1, Dialect::PostgreSql);
        $value = new JsonScalarConversion($input->source, $input);
        self::assertSame($input, $value->withFacts($value->facts)->value);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($input->facts);
    }
}
