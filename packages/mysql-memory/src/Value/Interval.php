<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * An interval of an INTERVAL expression: months and microseconds, read from a quantity of a unit.
 *
 * A simple unit reads its quantity as a number, rounded to an integer except for SECOND; a
 * compound unit reads the numbers of a string from its largest part, so `'1:30' HOUR_MINUTE` is
 * one hour and thirty minutes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 *
 * @visibility MySqlMemory
 */
final class Interval
{
    /**
     * @param int $months The months, negative for a negative interval
     * @param int $microseconds The microseconds, negative for a negative interval
     */
    public function __construct(public readonly int $months, public readonly int $microseconds)
    {
    }

    /**
     * Reads an interval from the text of its quantity and its unit, or answers null when the text holds no number.
     */
    public static function read(string $quantity, IntervalUnit $unit): ?self
    {
        $simple = [IntervalUnit::Microsecond->name => 1, IntervalUnit::Second->name => 1000000, IntervalUnit::Minute->name => 60000000, IntervalUnit::Hour->name => 3600000000, IntervalUnit::Day->name => 86400000000, IntervalUnit::Week->name => 604800000000];
        if (isset($simple[$unit->name])) {
            $number = NumericText::exact($quantity)->number;
            if ($unit === IntervalUnit::Second) {
                return new self(0, (int) Decimal::round(bcmul(Decimal::numeric($number), '1000000', 6), 0));
            }

            return new self(0, (int) Decimal::round($number, 0) * $simple[$unit->name]);
        }
        if ($unit === IntervalUnit::Month || $unit === IntervalUnit::Quarter || $unit === IntervalUnit::Year) {
            $count = (int) Decimal::round(NumericText::exact($quantity)->number, 0);

            return new self($count * match ($unit) {
                IntervalUnit::Month => 1,
                IntervalUnit::Quarter => 3,
                IntervalUnit::Year => 12,
            }, 0);
        }

        return self::compound($quantity, $unit);
    }

    /**
     * Reads the parts of a compound unit from a string.
     */
    public static function compound(string $quantity, IntervalUnit $unit): ?self
    {
        $text = trim($quantity);
        $negative = str_starts_with($text, '-');
        if (preg_match_all('/[0-9]+/', $text, $matches) === 0) {
            return null;
        }
        $numbers = array_map('intval', $matches[0]);
        $parts = match ($unit) {
            IntervalUnit::YearMonth => ['year', 'month'],
            IntervalUnit::DayHour => ['day', 'hour'],
            IntervalUnit::DayMinute => ['day', 'hour', 'minute'],
            IntervalUnit::DaySecond => ['day', 'hour', 'minute', 'second'],
            IntervalUnit::DayMicrosecond => ['day', 'hour', 'minute', 'second', 'micro'],
            IntervalUnit::HourMinute => ['hour', 'minute'],
            IntervalUnit::HourSecond => ['hour', 'minute', 'second'],
            IntervalUnit::HourMicrosecond => ['hour', 'minute', 'second', 'micro'],
            IntervalUnit::MinuteSecond => ['minute', 'second'],
            IntervalUnit::MinuteMicrosecond => ['minute', 'second', 'micro'],
            IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year, IntervalUnit::SecondMicrosecond => ['second', 'micro'],
        };
        $numbers = array_slice($numbers, 0, count($parts));
        $values = array_combine(array_slice($parts, count($parts) - count($numbers)), $numbers);
        if (isset($values['micro']) && preg_match('/[.]([0-9]+)\s*\z/', $text, $fraction) === 1) {
            $values['micro'] = (int) substr(str_pad($fraction[1], 6, '0'), 0, 6);
        }
        $months = ($values['year'] ?? 0) * 12 + ($values['month'] ?? 0);
        $micro = ((($values['day'] ?? 0) * 24 + ($values['hour'] ?? 0)) * 60 + ($values['minute'] ?? 0)) * 60000000 + ($values['second'] ?? 0) * 1000000 + ($values['micro'] ?? 0);

        return $negative ? new self(-$months, -$micro) : new self($months, $micro);
    }

    /**
     * Tells whether a unit moves a date by whole days or more.
     */
    public static function dated(IntervalUnit $unit): bool
    {
        return in_array($unit, [IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year, IntervalUnit::YearMonth], true);
    }
}
