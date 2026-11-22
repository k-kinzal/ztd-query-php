<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

#[CoversClass(IntegerRange::class)]
#[Small]
final class IntegerRangeTest extends TestCase
{
    public function testRangeExposesBothOrderedBounds(): void
    {
        $column = new ColumnReference(1);
        $lower = new RangeIntegerLiteral('-9223372036854775808');
        $upper = new NullLiteral();
        $range = new IntegerRange($column, $lower, $upper, true);

        self::assertSame($column, $range->subject);
        self::assertSame($lower, $range->lower);
        self::assertSame($upper, $range->upper);
        self::assertTrue($range->negated);
    }
}
