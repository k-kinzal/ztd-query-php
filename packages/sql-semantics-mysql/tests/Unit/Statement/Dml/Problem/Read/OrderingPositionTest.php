<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Read;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(OrderingPosition::class)]
#[Small]
final class OrderingPositionTest extends TestCase
{
    public function testMessageKeepsTheUnsignedRangeAndWrittenLeadingZeros(): void
    {
        self::assertSame("Unknown column '18446744073709551615' in 'order clause'", (new OrderingPosition(new NumberLiteral('18446744073709551615')))->message());
        self::assertSame("Unknown column '000' in 'order clause'", (new OrderingPosition(new NumberLiteral('000')))->message());
        self::assertSame("Unknown column '001' in 'order clause'", (new OrderingPosition(new NumberLiteral('001')))->message());
    }

    public function testValueExcludesDecimalOrderingExpressions(): void
    {
        $this->expectExceptionMessage('A write ordering position is an integer literal or a legacy boolean.');
        new OrderingPosition(new NumberLiteral('1.0'));
    }
}
