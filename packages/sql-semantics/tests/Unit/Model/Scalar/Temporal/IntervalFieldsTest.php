<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Temporal\IntervalFields;

#[CoversClass(IntervalFields::class)]
#[Medium]
final class IntervalFieldsTest extends TestCase
{
    public function testCalendarOnlyIncludesQuarterAndWeek(): void
    {
        self::assertTrue(IntervalFields::calendarOnly(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Quarter));
        self::assertTrue(IntervalFields::calendarOnly(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Week));
        self::assertFalse(IntervalFields::calendarOnly(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DayHour));
    }

    public function testClockOnlyKeepsDateFieldsOutOfTimeOnlyArithmetic(): void
    {
        self::assertTrue(IntervalFields::clockOnly(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::HourMicrosecond));
        self::assertFalse(IntervalFields::clockOnly(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DayMicrosecond));
    }

    public function testQuantityTypeDistinguishesIntegersFractionsAndCompositeStrings(): void
    {
        self::assertSame('bigint', IntervalFields::quantityType(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Month));
        self::assertSame('numeric', IntervalFields::quantityType(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Second));
        self::assertSame('varchar', IntervalFields::quantityType(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DaySecond));
    }
}
