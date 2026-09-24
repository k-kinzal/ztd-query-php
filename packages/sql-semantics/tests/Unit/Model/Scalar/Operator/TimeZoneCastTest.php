<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Operator\TimeZoneCast;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TimeZoneCast::class)]
#[Medium]
final class TimeZoneCastTest extends TestCase
{
    #[TestWith(['mysql-8.0.44', "CAST(at AT TIME ZONE 'UTC' AS DATETIME)", false, null])]
    #[TestWith(['mysql-8.4.7', "CAST(at AT TIME ZONE INTERVAL '+00:00' AS DATETIME(3))", true, 3])]
    #[TestWith(['mysql-9.1.0', "CAST(at AT TIME ZONE '+00:00' AS DATETIME(6))", false, 6])]
    public function testInputsKeepTheValueAndTheZone(string $version, string $sql, bool $interval, ?int $precision): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (at TIMESTAMP NOT NULL)'));
        $query = $binder->bind('SELECT ' . $sql . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $cast = $query->outputs[0]->expression;
        self::assertInstanceOf(TimeZoneCast::class, $cast);
        self::assertSame($interval, $cast->interval);
        self::assertSame($precision, $cast->precision);
        self::assertSame([$cast->operand, $cast->zone], $cast->inputs());
        self::assertSame('datetime', $cast->type->name);
        self::assertSame('SELECT ' . str_replace('(at ', '(`at` ', $sql) . ' FROM `t`', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAZoneThatIsNotAString(): void
    {
        $zone = Expression::literal(0, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $zone);
        $this->expectException(InvalidStructure::class);
        new TimeZoneCast($zone->source, $zone, $zone, false, null);
    }

    public function testInputsRejectAPrecisionAboveSix(): void
    {
        $zone = Expression::literal('UTC', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $zone);
        $this->expectException(InvalidStructure::class);
        new TimeZoneCast($zone->source, $zone, $zone, false, 7);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $zone = Expression::literal('UTC', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $zone);
        $this->expectException(InvalidStructure::class);
        new TimeZoneCast($zone->source, $zone, $zone, false, null);
    }

    public function testSpellingNamesTheCast(): void
    {
        $zone = Expression::literal('UTC', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $zone);
        self::assertSame('CAST', (new TimeZoneCast($zone->source, $zone, $zone, false, null))->spelling());
    }

    public function testWithFactsPreservesTheZone(): void
    {
        $zone = Expression::literal('UTC', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $zone);
        $cast = new TimeZoneCast($zone->source, $zone, $zone, true, 2);
        $copy = $cast->withFacts($cast->facts);
        self::assertNotSame($cast, $copy);
        self::assertTrue($copy->interval);
        self::assertSame(2, $copy->precision);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $zone = Expression::literal('UTC', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $zone);
        $cast = new TimeZoneCast($zone->source, $zone, $zone, false, null);
        $this->expectException(InvalidStructure::class);
        $cast->withFacts($zone->facts);
    }
}
