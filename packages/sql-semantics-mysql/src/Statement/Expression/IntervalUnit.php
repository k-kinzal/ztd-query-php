<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

/**
 * The unit of a temporal interval, as written after an INTERVAL quantity and in the functions that take a unit.
 *
 * Each case holds the unit keyword. The `SQL_TSI_` spellings of the simple
 * units are the same keywords to the server.
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
}
