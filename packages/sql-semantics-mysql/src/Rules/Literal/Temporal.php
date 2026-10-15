<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Literal;

/**
 * Normalizes temporal literals for resolved result names and execution.
 *
 * Shared by semantic naming and mysql-memory; no database engine code is used.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Temporal
{
    /**
     * Tells whether parts name a date that exists, or the zero date.
     */
    public static function valid(int $year, int $month, int $day): bool
    {
        if ($year === 0 && $month === 0 && $day === 0) {
            return true;
        }

        return $month >= 1 && $month <= 12 && $day >= 1 && $year <= 9999 && checkdate($month, $day, max(1, $year));
    }

    /**
     * Writes a date.
     */
    public static function date(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Writes a datetime with a number of fractional digits.
     */
    public static function dateTime(int $year, int $month, int $day, int $hour, int $minute, int $second, int $micro, int $decimals): string
    {
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second) . self::fraction($micro, $decimals);
    }

    /**
     * Writes a time of day or an interval of hours with a number of fractional digits.
     */
    public static function time(bool $negative, int $hours, int $minute, int $second, int $micro, int $decimals): string
    {
        return ($negative ? '-' : '') . sprintf('%02d:%02d:%02d', $hours, $minute, $second) . self::fraction($micro, $decimals);
    }

    /**
     * Writes the fractional seconds of a number of decimals, or nothing for none.
     */
    public static function fraction(int $micro, int $decimals): string
    {
        return $decimals > 0 ? '.' . substr(sprintf('%06d', $micro), 0, $decimals) : '';
    }

    /**
     * Reads the date or datetime at the start of a string, keeping every fractional digit written.
     *
     * Digits without delimiters must make up the whole text; a delimited value may be followed by
     * more text, answered as the rest. In a time context only whitespace separates the date from
     * the time, and digits without delimiters hold a time part only from twelve digits on.
     *
     * @return array{int, int, int, int, int, int, string, bool, string}|null Year, month, day, hour, minute, second, fractional digits, whether a time part was present, and the text after the value
     */
    public static function scanDateTime(string $text, bool $timeContext = false): ?array
    {
        $text = trim($text, " \t\n\r");
        $rest = '';
        if (preg_match('/\A([0-9]{2}|[0-9]{4})([0-9]{2})([0-9]{2})(?:T?([0-9]{2})([0-9]{2})([0-9]{2})?)?(?:\.([0-9]*))?\z/', $text, $parts) === 1 && strlen($text) >= 6) {
            if ($timeContext && strlen(explode('.', $text)[0]) < 12) {
                return null;
            }
        } elseif (preg_match('/\A([0-9]{1,4})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{1,2})(?:' . ($timeContext ? '\s+' : '[^0-9]+') . '([0-9]{1,2})(?:[^0-9]+([0-9]{1,2})(?:[^0-9]+([0-9]{1,2}))?)?)?(?:\.([0-9]*))?/', $text, $parts) === 1) {
            $rest = substr($text, strlen($parts[0]));
        } else {
            return null;
        }
        $year = (int) $parts[1];
        if (strlen($parts[1]) === 2) {
            $year += $year < 70 ? 2000 : 1900;
        }

        return [$year, (int) $parts[2], (int) $parts[3], (int) ($parts[4] ?? 0), (int) ($parts[5] ?? 0), (int) ($parts[6] ?? 0), $parts[7] ?? '', ($parts[4] ?? '') !== '', $rest];
    }

    /**
     * Reads the time at the start of a string, keeping every fractional digit written.
     *
     * @return array{bool, int, int, int, string, string}|null Negative, hours, minute, second, fractional digits, and the text after the value
     */
    public static function scanTime(string $text): ?array
    {
        $text = trim($text, " \t\n\r");
        if (preg_match('/\A(-)?(?:([0-9]+) +)?([0-9]+):([0-9]{1,2})(?::([0-9]{1,2}))?(?:\.([0-9]*))?/', $text, $match) === 1) {
            $hours = (int) ($match[2] === '' ? 0 : $match[2]) * 24 + (int) $match[3];

            return [$match[1] === '-', $hours, (int) $match[4], (int) ($match[5] ?? 0), $match[6] ?? '', substr($text, strlen($match[0]))];
        }
        if (preg_match('/\A(-)?([0-9]+)(?:\.([0-9]*))?/', $text, $match) === 1) {
            $number = str_pad($match[2], 6, '0', STR_PAD_LEFT);

            return [$match[1] === '-', (int) substr($number, 0, -4), (int) substr($number, -4, 2), (int) substr($number, -2), $match[3] ?? '', substr($text, strlen($match[0]))];
        }

        return null;
    }

    /**
     * Answers the microseconds fractional digits write: rounded half up at the seventh digit, or truncated; 1000000 is a whole second.
     */
    public static function micro(string $digits, bool $truncate): int
    {
        $micro = (int) substr(str_pad($digits, 6, '0'), 0, 6);

        return $micro + (!$truncate && strlen($digits) > 6 && $digits[6] >= '5' ? 1 : 0);
    }

    /**
     * Tells whether parts name a date a mode accepts: one that exists, the zero date unless
     * NO_ZERO_DATE is set, or a date with a zero month or day unless NO_ZERO_IN_DATE is set.
     * ALLOW_INVALID_DATES accepts days up to 31 without requiring that day in the month.
     */
    public static function accepted(int $year, int $month, int $day, bool $noZeroDate, bool $noZeroInDate, bool $allowInvalidDates = false): bool
    {
        if ($year === 0 && $month === 0 && $day === 0) {
            return !$noZeroDate;
        }
        if ($month === 0 || $day === 0) {
            return !$noZeroInDate && $month <= 12 && $day <= 31 && $year <= 9999;
        }

        return self::valid($year, $month, $day) || ($allowInvalidDates && $year <= 9999 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31);
    }

    /**
     * Answers the value of a DATE, TIME or TIMESTAMP literal, or null when its text is no value of the form.
     *
     * A DATE literal holds no time and a TIMESTAMP literal holds one; a TIME literal is a time of
     * at most 838:59:59. Fractional seconds beyond the sixth digit are rounded.
     *
     * @param string $form DATE, TIME or DATETIME
     * @param int $decimals The fractional digits of the type of the literal
     * @param bool $noZeroDate Whether NO_ZERO_DATE refuses the zero date
     * @param bool $noZeroInDate Whether NO_ZERO_IN_DATE refuses a zero month or day
     * @param bool $allowInvalidDates Whether calendar-invalid days within 1 through 31 are retained
     */
    public static function literal(string $form, string $text, int $decimals, bool $noZeroDate, bool $noZeroInDate, bool $allowInvalidDates = false): ?string
    {
        if ($form === 'TIME') {
            $time = self::scanTime($text);
            if ($time === null || $time[5] !== '' || $time[2] > 59 || $time[3] > 59) {
                return null;
            }
            $micro = self::micro($time[4], false);
            if ($time[1] > 838 || ($time[1] === 838 && $time[2] === 59 && $time[3] === 59 && $micro > 0)) {
                return null;
            }
            $seconds = $time[1] * 3600 + $time[2] * 60 + $time[3] + intdiv($micro, 1000000);

            return self::time($time[0], intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $micro % 1000000, $decimals);
        }
        $parts = self::scanDateTime($text);
        if ($parts === null || $parts[8] !== '' || $parts[7] !== ($form === 'DATETIME') || !self::accepted($parts[0], $parts[1], $parts[2], $noZeroDate, $noZeroInDate, $allowInvalidDates)) {
            return null;
        }
        if ($form === 'DATE') {
            return self::date($parts[0], $parts[1], $parts[2]);
        }
        if ($parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return null;
        }
        if ($allowInvalidDates && self::micro($parts[6], false) >= 1000000 && !self::valid($parts[0], $parts[1], $parts[2])) {
            return null;
        }
        $moment = self::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], self::micro($parts[6], false));

        return $moment === null ? null : self::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $decimals);
    }

    /**
     * Carries a whole second of microseconds into the date and time, or answers null when the year passes 9999.
     *
     * A date with a zero month or day carries no further than its hour.
     *
     * @return array{int, int, int, int, int, int, int}|null
     */
    public static function carry(int $year, int $month, int $day, int $hour, int $minute, int $second, int $micro): ?array
    {
        if ($micro < 1000000) {
            return [$year, $month, $day, $hour, $minute, $second, $micro];
        }
        if ($month === 0 || $day === 0) {
            $seconds = min(86399, $hour * 3600 + $minute * 60 + $second + 1);

            return [$year, $month, $day, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, 0];
        }
        if ($year === 9999 && $month === 12 && $day === 31 && $hour === 23 && $minute === 59 && $second === 59) {
            return null;
        }
        $seconds = $hour * 3600 + $minute * 60 + $second + 1;
        if ($seconds < 86400) {
            return [$year, $month, $day, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, 0];
        }
        $length = match ($month) {
            2 => $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0) ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
        [$year, $month, $day] = $day < $length ? [$year, $month, $day + 1] : ($month < 12 ? [$year, $month + 1, 1] : [$year + 1, 1, 1]);

        return [$year, $month, $day, 0, 0, 0, 0];
    }
}
