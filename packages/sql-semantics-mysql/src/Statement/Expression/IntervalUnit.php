<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

/**
 * The unit of a temporal interval, as written after an INTERVAL quantity and in the functions that take a unit.
 *
 * Each case holds the written unit keyword. The eight SQL_TSI aliases retain
 * their spelling; canonical() gives their shared temporal meaning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 *
 * @visibility public
 * @example Reading the keyword of a unit
 *     \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour->value // => 'DAY_HOUR'
 */
enum IntervalUnit: string
{
    case Microsecond = 'MICROSECOND';
    case Second = 'SECOND';
    case Minute = 'MINUTE';
    case Hour = 'HOUR';
    case Day = 'DAY';
    case Week = 'WEEK';
    case Month = 'MONTH';
    case Quarter = 'QUARTER';
    case Year = 'YEAR';
    case SecondMicrosecond = 'SECOND_MICROSECOND';
    case MinuteMicrosecond = 'MINUTE_MICROSECOND';
    case MinuteSecond = 'MINUTE_SECOND';
    case HourMicrosecond = 'HOUR_MICROSECOND';
    case HourSecond = 'HOUR_SECOND';
    case HourMinute = 'HOUR_MINUTE';
    case DayMicrosecond = 'DAY_MICROSECOND';
    case DaySecond = 'DAY_SECOND';
    case DayMinute = 'DAY_MINUTE';
    case DayHour = 'DAY_HOUR';
    case YearMonth = 'YEAR_MONTH';
    case SqlTsiSecond = 'SQL_TSI_SECOND';
    case SqlTsiMinute = 'SQL_TSI_MINUTE';
    case SqlTsiHour = 'SQL_TSI_HOUR';
    case SqlTsiDay = 'SQL_TSI_DAY';
    case SqlTsiWeek = 'SQL_TSI_WEEK';
    case SqlTsiMonth = 'SQL_TSI_MONTH';
    case SqlTsiQuarter = 'SQL_TSI_QUARTER';
    case SqlTsiYear = 'SQL_TSI_YEAR';

    /**
     * Answers the temporal unit independently of its SQL_TSI keyword alias.
     *
     * @return self::Microsecond|self::Second|self::Minute|self::Hour|self::Day|self::Week|self::Month|self::Quarter|self::Year|self::SecondMicrosecond|self::MinuteMicrosecond|self::MinuteSecond|self::HourMicrosecond|self::HourSecond|self::HourMinute|self::DayMicrosecond|self::DaySecond|self::DayMinute|self::DayHour|self::YearMonth
     */
    public function canonical(): self
    {
        return match ($this) {
            self::SqlTsiSecond => self::Second,
            self::SqlTsiMinute => self::Minute,
            self::SqlTsiHour => self::Hour,
            self::SqlTsiDay => self::Day,
            self::SqlTsiWeek => self::Week,
            self::SqlTsiMonth => self::Month,
            self::SqlTsiQuarter => self::Quarter,
            self::SqlTsiYear => self::Year,
            self::Microsecond, self::Second, self::Minute, self::Hour, self::Day, self::Week, self::Month, self::Quarter, self::Year,
            self::SecondMicrosecond, self::MinuteMicrosecond, self::MinuteSecond, self::HourMicrosecond, self::HourSecond, self::HourMinute,
            self::DayMicrosecond, self::DaySecond, self::DayMinute, self::DayHour, self::YearMonth => $this,
        };
    }
}
