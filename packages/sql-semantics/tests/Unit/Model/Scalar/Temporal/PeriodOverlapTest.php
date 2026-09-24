<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Temporal\PeriodOverlap;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(PeriodOverlap::class)]
#[Medium]
final class PeriodOverlapTest extends TestCase
{
    public function testInputsListBothPeriodsInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (s DATE, e DATE)'));
        $query = $binder->bind("SELECT (s, e) OVERLAPS (DATE '2001-01-01', INTERVAL '1 day') FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $overlap = $query->outputs[0]->expression;
        self::assertInstanceOf(PeriodOverlap::class, $overlap);
        self::assertSame([$overlap->leftStart, $overlap->leftEnd, $overlap->rightStart, $overlap->rightEnd], $overlap->inputs());
        self::assertSame('s', $overlap->leftStart->columnBinding()?->column->name);
        self::assertSame('interval', $overlap->rightEnd->type->name);
        self::assertSame('boolean', $overlap->type->name);
        self::assertSame(Nullability::MaybeNull, $overlap->nullability);
        self::assertSame('SELECT (("s", "e") OVERLAPS(CAST(\'2001-01-01\' AS date), CAST(\'1 day\' AS INTERVAL))) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new PeriodOverlap($value->source, $value, $value, $value, $value);
    }

    public function testSpellingNamesTheOperator(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('OVERLAPS', (new PeriodOverlap($value->source, $value, $value, $value, $value))->spelling());
    }

    public function testWithFactsPreservesThePeriods(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $overlap = new PeriodOverlap($value->source, $value, $value, $value, $value);
        $copy = $overlap->withFacts($overlap->facts);
        self::assertNotSame($overlap, $copy);
        self::assertSame($value, $copy->rightEnd);
        $this->expectException(InvalidStructure::class);
        $overlap->withFacts($value->facts);
    }
}
