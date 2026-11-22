<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;

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
}
