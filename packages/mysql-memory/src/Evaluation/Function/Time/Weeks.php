<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

/**
 * Numbers the weeks of a year in each mode of WEEK.
 *
 * Bit 0 of the mode makes Monday the first day of the week, else Sunday; bit 1 numbers the weeks
 * from 1, a day before week 1 then being in the last week of the year before, else from 0. Week 1
 * is the first week with four or more days in the year when the first-day bit and bit 2 differ,
 * else the first week that starts in the year; with four days and numbering from 1, the last days
 * of a year can be in week 1 of the next.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_week.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Weeks
{
    /**
     * Answers the week of a date in a mode, and the year the week belongs to.
     *
     * The days from the start of week 1 are counted as an unsigned 32-bit number, as the server
     * counts them, so a date with a zero month or day, which DATE_FORMAT takes, can have a week far
     * beyond 53.
     *
     * @return array{int, int}
     */
    public function week(int $year, int $month, int $day, int $mode): array
    {
        if (($mode & 1) === 0) {
            $mode ^= 4;
        }
        $monday = ($mode & 1) === 1;
        $yearly = ($mode & 2) === 2;
        $starting = ($mode & 4) === 4;
        $number = Readings::dayNumber($year, $month, $day);
        $first = Readings::dayNumber($year, 1, 1);
        $weekday = (($first + ($monday ? 5 : 6)) % 7 + 7) % 7;
        if ($month === 1 && $day <= 7 - $weekday) {
            if (!$yearly && (($starting && $weekday !== 0) || (!$starting && $weekday >= 4))) {
                return [0, $year];
            }
            $yearly = true;
            $year--;
            $length = $this->length($year);
            $first -= $length;
            $weekday = (($weekday + 53 * 7 - $length) % 7 + 7) % 7;
        }
        $days = (($starting && $weekday !== 0) || (!$starting && $weekday >= 4) ? $number - ($first + 7 - $weekday) : $number - ($first - $weekday)) & 0xFFFFFFFF;
        if ($yearly && $days >= 52 * 7) {
            $weekday = ($weekday + $this->length($year)) % 7;
            if ((!$starting && $weekday < 4) || ($starting && $weekday === 0)) {
                return [1, $year + 1];
            }
        }

        return [intdiv($days, 7) + 1, $year];
    }

    /**
     * Answers the number of days of a year; year 0 has 365.
     */
    public function length(int $year): int
    {
        return ($year & 3) === 0 && ($year % 100 !== 0 || ($year % 400 === 0 && $year !== 0)) ? 366 : 365;
    }
}
