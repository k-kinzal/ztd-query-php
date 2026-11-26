<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;

#[CoversClass(IntegerComparison::class)]
#[Small]
final class IntegerComparisonTest extends TestCase
{
    public function testTheComparisonRetainsTheOperandPositions(): void
    {
        $slot = new ColumnReference(2);
        $literal = new IntegerLiteral('1');
        $predicate = new IntegerComparison(IntegerOperator::LessOrEqual, $slot, $literal);

        self::assertSame($slot, $predicate->left);
        self::assertSame($literal, $predicate->right);
        self::assertSame(IntegerOperator::LessOrEqual, $predicate->operator);
    }
}
