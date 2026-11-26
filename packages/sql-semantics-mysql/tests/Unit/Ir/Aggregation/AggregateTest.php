<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;

#[CoversClass(Aggregate::class)]
#[Small]
final class AggregateTest extends TestCase
{
    public function testRetainsTheDistinctArgument(): void
    {
        $slot = new ColumnReference(1);
        $aggregate = new Aggregate(AggregateOperator::Count, $slot, true);

        self::assertSame($slot, $aggregate->argument);
        self::assertTrue($aggregate->distinct);
    }

    public function testRejectsDistinctStar(): void
    {
        $this->expectExceptionMessage('Only a nondistinct COUNT can omit the aggregate argument.');

        new Aggregate(AggregateOperator::Count, distinct: true);
    }

    public function testCountAcceptsAnExactVarcharArgument(): void
    {
        $reference = new TextColumnReference(1);
        $aggregate = new Aggregate(AggregateOperator::Count, $reference, true);

        self::assertSame($reference, $aggregate->argument);
        self::assertTrue($aggregate->distinct);
    }

    public function testSumCannotAcceptANativeVarcharArgument(): void
    {
        $this->expectExceptionMessage('SUM requires a signed INT argument.');

        new Aggregate(AggregateOperator::Sum, new TextColumnReference(1));
    }

    public function testMinPreservesTheNativeVarcharPosition(): void
    {
        $reference = new TextColumnReference(3);
        $value = new Aggregate(AggregateOperator::Min, $reference);

        self::assertSame($reference, $value->argument);
        self::assertSame(AggregateOperator::Min, $value->operator);
    }
}
