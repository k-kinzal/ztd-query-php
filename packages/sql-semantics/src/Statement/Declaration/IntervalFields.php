<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * The fields an interval declaration restricts a duration to.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Statement\Declaration\IntervalFields::DayToSecond->value // => 'day to second'
 *
 * @visibility public
 */
enum IntervalFields: string
{
    case Year = 'year';
    case Month = 'month';
    case Day = 'day';
    case Hour = 'hour';
    case Minute = 'minute';
    case Second = 'second';
    case YearToMonth = 'year to month';
    case DayToHour = 'day to hour';
    case DayToMinute = 'day to minute';
    case DayToSecond = 'day to second';
    case HourToMinute = 'hour to minute';
    case HourToSecond = 'hour to second';
    case MinuteToSecond = 'minute to second';
}
