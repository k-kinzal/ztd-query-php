<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * A PostgreSQL interval field range.
 * @visibility public
 * @example Classifying an interval field range
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(d INTERVAL DAY TO SECOND)')->tables[0]->columns[0]->type;
 *     $type->identity->fields // => \SqlSemantics\Type\Identity\IntervalFields::DayToSecond
 */
enum IntervalFields: string
{
    case All = '';
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
}
