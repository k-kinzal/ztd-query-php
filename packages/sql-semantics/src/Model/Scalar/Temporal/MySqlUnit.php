<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * A named extraction or arithmetic interval unit in MySQL.
 * @visibility public
 * @example Selecting a calendar field
 *     \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Year->value // => 'YEAR'
 */
enum MySqlUnit: string
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
