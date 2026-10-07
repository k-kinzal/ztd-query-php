<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Proleptic Gregorian arithmetic on dates and times held as their parts.
 *
 * @visibility MySqlMemory
 */
final class Calendar
{
    /**
     * Answers the day number of a date: days since 0000-01-01 counted as the server counts them (TO_DAYS).
     */
    public static function days(int $year, int $month, int $day): int
    {
        if ($year === 0 && $month === 0) {
            return 0;
        }
        $before = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $previous = $year - 1;
        $leaps = $year > 0 ? intdiv($previous, 4) - intdiv($previous, 100) + intdiv($previous, 400) : 0;
        $leap = $year > 0 && (($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0);

        return 365 * $year + $leaps + $before[$month - 1] + $day + ($leap && $month > 2 ? 1 : 0);
    }

    /**
     * Answers the date of a day number (FROM_DAYS).
     *
     * @return array{int, int, int}
     */
    public static function date(int $days): array
    {
        if ($days <= 365) {
            return [0, 0, 0];
        }
        $year = intdiv($days * 100, 36525);
        $start = self::days($year, 1, 1);
        while ($start > $days) {
            $year--;
            $start = self::days($year, 1, 1);
        }
        while (self::days($year + 1, 1, 1) <= $days) {
            $year++;
        }
        $start = self::days($year, 1, 1);
        $month = 1;
        while ($month < 12 && self::days($year, $month + 1, 1) <= $days) {
            $month++;
        }

        return [$year, $month, $days - self::days($year, $month, 1) + 1];
    }

    /**
     * Answers the number of days of a month.
     */
    public static function monthLength(int $year, int $month): int
    {
        return $month === 2 ? (($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0 ? 29 : 28) : (in_array($month, [4, 6, 9, 11], true) ? 30 : 31);
    }

    /**
     * Adds a number of months, keeping the day within the length of the month reached.
     *
     * @return array{int, int, int}|null The year, month and day, or null outside years 0 to 9999
     */
    public static function addMonths(int $year, int $month, int $day, int $months): ?array
    {
        $index = $year * 12 + $month - 1 + $months;
        if ($index < 0 || $index >= 120000) {
            return null;
        }
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;

        return [$year, $month, min($day, self::monthLength($year, $month))];
    }

    /**
     * Adds microseconds to a date and time, answering the parts reached, or null outside years 0 to 9999.
     *
     * @return array{int, int, int, int, int, int, int}|null
     */
    public static function addMicroseconds(int $year, int $month, int $day, int $hour, int $minute, int $second, int $micro, int $delta): ?array
    {
        $total = ((self::days($year, $month, $day) * 86400 + $hour * 3600 + $minute * 60 + $second) * 1000000) + $micro + $delta;
        $days = intdiv($total, 86400000000);
        $rest = $total % 86400000000;
        if ($rest < 0) {
            $rest += 86400000000;
            $days--;
        }
        if ($days <= 365 || $days > self::days(9999, 12, 31)) {
            return null;
        }
        [$year, $month, $day] = self::date($days);
        $seconds = intdiv($rest, 1000000);

        return [$year, $month, $day, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $rest % 1000000];
    }
}
