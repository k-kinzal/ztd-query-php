<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

/**
 * The fields an interval type or interval constant is restricted to.
 *
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html#DATATYPE-INTERVAL-INPUT.
 *
 * @visibility public
 * @example Telling whether a restriction keeps seconds
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields::DayToSecond->seconds() // => true
 */
enum IntervalFields: string
{
    case Year = 'YEAR';
    case Month = 'MONTH';
    case Day = 'DAY';
    case Hour = 'HOUR';
    case Minute = 'MINUTE';
    case Second = 'SECOND';
    case YearToMonth = 'YEAR TO MONTH';
    case DayToHour = 'DAY TO HOUR';
    case DayToMinute = 'DAY TO MINUTE';
    case DayToSecond = 'DAY TO SECOND';
    case HourToMinute = 'HOUR TO MINUTE';
    case HourToSecond = 'HOUR TO SECOND';
    case MinuteToSecond = 'MINUTE TO SECOND';

    /**
     * Tells whether the restriction ends in the seconds field, which alone takes a precision.
     */
    public function seconds(): bool
    {
        return str_ends_with($this->value, 'SECOND');
    }
}
