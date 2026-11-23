<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedComparison;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;

#[CoversClass(GroupedComparison::class)]
#[Small]
final class GroupedComparisonTest extends TestCase
{
    public function testRetainsOrderedGroupOperands(): void
    {
        $threshold = new IntegerLiteral('2');
        $count = new Aggregate(AggregateOperator::Count);
        $relative = new GroupedComparison(IntegerOperator::Less, $threshold, $count);

        self::assertSame($threshold, $relative->left);
        self::assertSame($count, $relative->right);
        self::assertSame(IntegerOperator::Less, $relative->operator);
    }
}
