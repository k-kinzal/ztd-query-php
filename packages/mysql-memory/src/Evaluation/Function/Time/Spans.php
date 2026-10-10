<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Value\Calendar;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * TIMESTAMPDIFF: the whole units from one date and time to another.
 *
 * Units up to a week are counted from the microseconds between the values, truncated toward
 * zero. Months, quarters and years count whole months: the months between the dates, less one
 * when the later value's day and time of the month come before the earlier one's (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_timestampdiff.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Spans
{
    /**
     * The microseconds of each unit up to a week.
     */
    public const MICROSECONDS = ['MICROSECOND' => 1, 'SECOND' => 1000000, 'MINUTE' => 60000000, 'HOUR' => 3600000000, 'DAY' => 86400000000, 'WEEK' => 604800000000];

    /**
     * Answers the whole units from the first value to the second, or null.
     */
    public function difference(Frame $frame, IntervalUnit $unit, Evaluable $first, Evaluable $second): ?int
    {
        $readings = new Readings();
        $from = $readings->moment($frame, $first, Zeros::Refused);
        if ($from === null) {
            return null;
        }
        $to = $readings->moment($frame, $second, Zeros::Refused);
        if ($to === null) {
            return null;
        }
        $size = self::MICROSECONDS[$unit->value] ?? null;
        if ($size !== null) {
            $micro = (Calendar::epoch($to[0], $to[1], $to[2], $to[3], $to[4], $to[5]) - Calendar::epoch($from[0], $from[1], $from[2], $from[3], $from[4], $from[5])) * 1000000 + $to[6] - $from[6];

            return intdiv($micro, $size);
        }
        $months = ($to[0] - $from[0]) * 12 + $to[1] - $from[1];
        $later = $this->within($to);
        $earlier = $this->within($from);
        if ($months > 0 && $later < $earlier) {
            $months--;
        } elseif ($months < 0 && $later > $earlier) {
            $months++;
        }

        return intdiv($months, match ($unit) {
            IntervalUnit::Quarter => 3,
            IntervalUnit::Year => 12,
            IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month,
            IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::MinuteSecond, IntervalUnit::HourMicrosecond, IntervalUnit::HourSecond,
            IntervalUnit::HourMinute, IntervalUnit::DayMicrosecond, IntervalUnit::DaySecond, IntervalUnit::DayMinute, IntervalUnit::DayHour, IntervalUnit::YearMonth => 1,
        });
    }

    /**
     * Answers the microseconds of a value from the start of its month.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function within(array $parts): int
    {
        return ((($parts[2] * 24 + $parts[3]) * 60 + $parts[4]) * 60 + $parts[5]) * 1000000 + $parts[6];
    }
}
