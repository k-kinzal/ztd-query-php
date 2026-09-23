<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * Describes the calendar and clock fields that a MySQL interval unit contains.
 * @visibility SqlSemantics
 */
final class IntervalFields
{
    /**
     * Identifies intervals without a clock field.
     */
    public static function calendarOnly(MySqlUnit $unit): bool
    {
        return in_array($unit, [MySqlUnit::Year, MySqlUnit::Quarter, MySqlUnit::Month, MySqlUnit::Week, MySqlUnit::Day, MySqlUnit::YearMonth], true);
    }

    /**
     * Identifies intervals without a calendar field.
     */
    public static function clockOnly(MySqlUnit $unit): bool
    {
        return in_array($unit, [MySqlUnit::Hour, MySqlUnit::Minute, MySqlUnit::Second, MySqlUnit::Microsecond, MySqlUnit::HourMinute, MySqlUnit::HourSecond, MySqlUnit::HourMicrosecond, MySqlUnit::MinuteSecond, MySqlUnit::MinuteMicrosecond, MySqlUnit::SecondMicrosecond], true);
    }

    /**
     * Returns the default parameter family required by the interval's lexical representation.
     */
    public static function quantityType(MySqlUnit $unit): string
    {
        return match ($unit) {
            MySqlUnit::Year, MySqlUnit::Quarter, MySqlUnit::Month, MySqlUnit::Week, MySqlUnit::Day, MySqlUnit::Hour, MySqlUnit::Minute, MySqlUnit::Microsecond => 'bigint',
            MySqlUnit::Second => 'numeric',
            MySqlUnit::YearMonth, MySqlUnit::DayHour, MySqlUnit::DayMinute, MySqlUnit::DaySecond, MySqlUnit::DayMicrosecond, MySqlUnit::HourMinute, MySqlUnit::HourSecond, MySqlUnit::HourMicrosecond, MySqlUnit::MinuteSecond, MySqlUnit::MinuteMicrosecond, MySqlUnit::SecondMicrosecond => 'varchar',
        };
    }
}
