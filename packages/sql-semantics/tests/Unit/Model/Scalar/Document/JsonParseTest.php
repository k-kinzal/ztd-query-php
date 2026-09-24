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
use SqlSemantics\Model\Scalar\Document\JsonParse;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonParse::class)]
#[Medium]
final class JsonParseTest extends TestCase
{
    public function testInputsListTheParsedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d text)'));
        $query = $binder->bind('SELECT JSON(d WITH UNIQUE) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonParse::class, $value);
        self::assertTrue($value->uniqueKeys);
        self::assertNull($value->input->format);
        self::assertSame([$value->input->expression], $value->inputs());
        self::assertSame('json', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame('SELECT JSON("d" WITH UNIQUE KEYS) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonParse(Expression::literal('{}', Dialect::MySql)->source, new Input(Expression::literal('{}', Dialect::MySql)));
    }

    public function testSpellingIdentifiesJson(): void
    {
        $value = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('JSON', (new JsonParse($value->source, new Input($value)))->spelling());
    }

    public function testWithFactsPreservesTheKeyUniqueness(): void
    {
        $input = Expression::literal('{}', Dialect::PostgreSql);
        $value = new JsonParse($input->source, new Input($input), true);
        self::assertTrue($value->withFacts($value->facts)->uniqueKeys);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($input->facts);
    }
}
