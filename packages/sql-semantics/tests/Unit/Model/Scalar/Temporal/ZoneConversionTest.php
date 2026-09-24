<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Temporal\ZoneConversion;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ZoneConversion::class)]
#[Medium]
final class ZoneConversionTest extends TestCase
{
    #[TestWith(["'2001-01-01'::timestamptz AT TIME ZONE 'UTC'", 'timestamp', 2])]
    #[TestWith(['LOCALTIMESTAMP AT LOCAL', 'timestamptz', 1])]
    #[TestWith(["CURRENT_DATE AT TIME ZONE INTERVAL '1 hour'", 'timestamptz', 2])]
    #[TestWith(['LOCALTIME AT LOCAL', 'timetz', 1])]
    #[TestWith(["'x' AT LOCAL", 'unknown', 1])]
    public function testInputsListTheValueAndTheWrittenZone(string $expression, string $type, int $inputs): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ' . $expression);
        self::assertInstanceOf(BoundSelect::class, $query);
        $conversion = $query->outputs[0]->expression;
        self::assertInstanceOf(ZoneConversion::class, $conversion);
        self::assertSame($type, $conversion->type->name);
        self::assertCount($inputs, $conversion->inputs());
        self::assertSame($conversion->value, $conversion->inputs()[0]);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new ZoneConversion($value->source, $value, null);
    }

    public function testSpellingDistinguishesTheSessionZone(): void
    {
        $value = Expression::literal('UTC', Dialect::PostgreSql);
        self::assertSame('AT LOCAL', (new ZoneConversion($value->source, $value, null))->spelling());
        self::assertSame('AT TIME ZONE', (new ZoneConversion($value->source, $value, $value))->spelling());
    }

    public function testWithFactsPreservesTheZone(): void
    {
        $value = Expression::literal('UTC', Dialect::PostgreSql);
        $conversion = new ZoneConversion($value->source, $value, $value);
        $copy = $conversion->withFacts($conversion->facts);
        self::assertNotSame($conversion, $copy);
        self::assertSame($value, $copy->zone);
        $this->expectException(InvalidStructure::class);
        $conversion->withFacts($value->facts);
    }
}
