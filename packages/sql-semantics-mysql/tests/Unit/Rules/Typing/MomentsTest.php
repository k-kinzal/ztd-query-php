<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Moments;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Moments::class)]
#[Small]
final class MomentsTest extends TestCase
{
    public function testShiftedKeepsADateForDatedUnitsOnly(): void
    {
        $moments = new Moments(new Settings(Collation::known('utf8mb4_0900_ai_ci')));
        $date = new Domain(Kind::Date, Field::Date, 10);

        self::assertEquals($date, $moments->shifted($date, IntervalUnit::Month));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 19), $moments->shifted($date, IntervalUnit::Hour));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 26, 6), $moments->shifted(new Domain(Kind::DateTime, Field::DateTime, 21, 1), IntervalUnit::Microsecond));
        self::assertEquals(Domain::string(29, Collation::known('utf8mb4_0900_ai_ci'), Field::String, Coercibility::Coercible), $moments->shifted(Domain::integer(), IntervalUnit::Day));
    }

    public function testShiftedMovesATimeByDaysToADatetimeAndKeepsTheFractionOfSeconds(): void
    {
        $moments = new Moments(new Settings(Collation::known('utf8mb4_0900_ai_ci')));
        $time = new Domain(Kind::Time, Field::Time, 10);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 19), $moments->shifted($time, IntervalUnit::Day));
        self::assertEquals(new Domain(Kind::Time, Field::Time, 17, 6), $moments->shifted($time, IntervalUnit::DayMicrosecond));
        self::assertEquals($time, $moments->shifted($time, IntervalUnit::Day, 0, true));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 21, 1), $moments->shifted(new Domain(Kind::DateTime, Field::DateTime, 19), IntervalUnit::Second, 1));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 19), $moments->shifted(new Domain(Kind::DateTime, Field::DateTime, 19), IntervalUnit::Minute, 1));
    }

    public function testQuantityCountsTheFractionalDigitsOfANumberOfUnits(): void
    {
        $moments = new Moments(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertSame([0, 3, 6, 6, 0], [$moments->quantity(Domain::integer()), $moments->quantity(Domain::decimal(10, 3)), $moments->quantity(Domain::double()), $moments->quantity(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))), $moments->quantity(null)]);
    }

    public function testExtractCountsThePartsAndASign(): void
    {
        $moments = new Moments(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals(Domain::integer(Field::LongLong, 7), $moments->extract(IntervalUnit::YearMonth));
        self::assertEquals(Domain::integer(Field::LongLong, 5), $moments->extract(IntervalUnit::Year));
    }

    public function testClockKeepsThePrecision(): void
    {
        $moments = new Moments(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3), $moments->clock(Clock::Now, 3));
        self::assertEquals(new Domain(Kind::Date, Field::Date, 10), $moments->clock(Clock::CurrentDate, 0));
        self::assertEquals(new Domain(Kind::Time, Field::Time, 11, 2), $moments->clock(Clock::CurrentTime, 2));
    }
}
