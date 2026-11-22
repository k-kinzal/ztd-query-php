<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\CountComparison;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;

#[CoversClass(CountComparison::class)]
#[Small]
final class CountComparisonTest extends TestCase
{
    public function testRetainsTheWrittenRolesAndNumberedBinding(): void
    {
        $count = new Count();
        $parameter = new Parameter('02');
        $filter = new CountComparison($count, ComparisonOperator::Less, $parameter, true);

        self::assertSame($count, $filter->count);
        self::assertSame(ComparisonOperator::Less, $filter->operator);
        self::assertSame($parameter, $filter->threshold);
        self::assertTrue($filter->thresholdFirst);
    }
}
