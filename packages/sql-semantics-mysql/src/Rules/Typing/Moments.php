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
     */
    public function shifted(Domain $operand, IntervalUnit $unit): Domain
    {
        $micro = in_array($unit, self::MICRO, true) ? 6 : 0;
        $fraction = $micro > 0 ? 7 : 0;

        return match ($operand->kind) {
            Kind::Date => in_array($unit, self::DATED, true) ? new Domain(Kind::Date, Field::Date, 10) : new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $micro),
            Kind::DateTime => new Domain(Kind::DateTime, Field::DateTime, max($operand->length, 19 + $fraction), max($operand->decimals, $micro)),
            Kind::Time => new Domain(Kind::Time, Field::Time, max($operand->length, 10 + $fraction), max($operand->decimals, $micro)),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::string(29, $this->settings->connection, Field::String, Coercibility::Coercible),
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
