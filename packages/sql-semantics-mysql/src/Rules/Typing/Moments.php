<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the types of date and time arithmetic, EXTRACT and the clock functions.
 *
 * A date moved by days, weeks, months, quarters or years stays a date and becomes a datetime for
 * a smaller unit; a datetime or a time stays what it is, with microseconds when the unit has
 * them; anything else becomes a string of 29 characters. EXTRACT gives a BIGINT as wide as the
 * parts of its unit and a sign. NOW and its synonyms are datetimes, CURDATE a date, CURTIME a
 * time, with the fractional digits a precision asks for.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Moments
{
    private const DATED = [IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year, IntervalUnit::YearMonth];

    private const MICRO = [IntervalUnit::Microsecond, IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::HourMicrosecond, IntervalUnit::DayMicrosecond];

    private const PARTS = [
        'YEAR' => 4, 'MONTH' => 2, 'DAY' => 2, 'HOUR' => 2, 'MINUTE' => 2, 'SECOND' => 2, 'MICROSECOND' => 6, 'WEEK' => 2, 'QUARTER' => 1,
        'YEAR_MONTH' => 6, 'DAY_HOUR' => 4, 'DAY_MINUTE' => 6, 'DAY_SECOND' => 8, 'HOUR_MINUTE' => 4, 'HOUR_SECOND' => 6, 'MINUTE_SECOND' => 4,
        'DAY_MICROSECOND' => 14, 'HOUR_MICROSECOND' => 12, 'MINUTE_MICROSECOND' => 10, 'SECOND_MICROSECOND' => 8,
    ];

    /**
     * @param Settings $settings The session the values are resolved in
     */
    public function __construct(public readonly Settings $settings)
    {
    }

    /**
     * Resolves a value moved by an interval of a unit.
     *
     * A number of seconds keeps its fractional digits. A TIME moved by days or a larger unit, other
     * than DAY_MICROSECOND, becomes a DATETIME, except in MySQL 5.6 and 5.7 (verified on live
     * 5.7.44 and 8.4.7 servers).
     *
     * @param int $quantity The fractional digits of the number of units (quantity())
     * @param bool $legacy Whether MySQL 5.6 or 5.7 resolves it
     */
    public function shifted(Domain $operand, IntervalUnit $unit, int $quantity = 0, bool $legacy = false): Domain
    {
        $micro = in_array($unit, self::MICRO, true) ? 6 : ($unit === IntervalUnit::Second ? $quantity : 0);
        $fraction = $micro > 0 ? $micro + 1 : 0;
        $dated = !$legacy && in_array($unit, [...self::DATED, IntervalUnit::DaySecond, IntervalUnit::DayMinute, IntervalUnit::DayHour], true);

        return match ($operand->kind) {
            Kind::Date => in_array($unit, self::DATED, true) ? new Domain(Kind::Date, Field::Date, 10) : new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $micro),
            Kind::DateTime => new Domain(Kind::DateTime, Field::DateTime, max($operand->length, 19 + $fraction), max($operand->decimals, $micro)),
            Kind::Time => $dated ? new Domain(Kind::DateTime, Field::DateTime, 19 + (max($operand->decimals, $micro) > 0 ? max($operand->decimals, $micro) + 1 : 0), max($operand->decimals, $micro)) : new Domain(Kind::Time, Field::Time, max($operand->length, 10 + $fraction), max($operand->decimals, $micro)),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::string(29, $this->settings->connection, Field::String, Coercibility::Coercible),
        };
    }

    /**
     * Answers the fractional digits a number of units carries: the scale of a DECIMAL, at most six, six for a DOUBLE or a string, none otherwise.
     */
    public function quantity(?Domain $quantity): int
    {
        if ($quantity === null) {
            return 0;
        }

        return match ($quantity->kind) {
            Kind::Decimal => min(6, $quantity->decimals),
            Kind::Double, Kind::String, Kind::Json => 6,
            Kind::Integer, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Bit, Kind::Null => 0,
        };
    }

    /**
     * Resolves EXTRACT of a unit.
     */
    public function extract(IntervalUnit $unit): Domain
    {
        return Domain::integer(Field::LongLong, self::PARTS[$unit->value] + 1);
    }

    /**
     * Resolves a clock function with the fractional digits a precision asks for.
     */
    public function clock(Clock $clock, int $decimals): Domain
    {
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($clock) {
            Clock::CurrentDate, Clock::UtcDate => new Domain(Kind::Date, Field::Date, 10),
            Clock::CurrentTime, Clock::UtcTime => new Domain(Kind::Time, Field::Time, 8 + $fraction, $decimals),
            Clock::Now, Clock::SystemDate, Clock::UtcTimestamp => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
        };
    }
}
