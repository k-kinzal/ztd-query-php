<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * A named extraction unit in PostgreSQL.
 * @visibility public
 * @example Selecting a calendar field
 *     \SqlSemantics\Model\Scalar\Temporal\PostgreSqlField::Year->value // => 'YEAR'
 */
enum PostgreSqlField: string
{
    case Century = 'CENTURY';
    case Day = 'DAY';
    case Decade = 'DECADE';
    case Dow = 'DOW';
    case Doy = 'DOY';
    case Epoch = 'EPOCH';
    case Hour = 'HOUR';
    case Isodow = 'ISODOW';
    case Isoyear = 'ISOYEAR';
    case Julian = 'JULIAN';
    case Microseconds = 'MICROSECONDS';
    case Millennium = 'MILLENNIUM';
    case Milliseconds = 'MILLISECONDS';
    case Minute = 'MINUTE';
    case Month = 'MONTH';
    case Quarter = 'QUARTER';
    case Second = 'SECOND';
    case Timezone = 'TIMEZONE';
    case TimezoneHour = 'TIMEZONE_HOUR';
    case TimezoneMinute = 'TIMEZONE_MINUTE';
    case Week = 'WEEK';
    case Year = 'YEAR';
}
