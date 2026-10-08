<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Reads and writes dates, times and datetimes in the canonical text the emulator holds them in.
 *
 * A date is `YYYY-MM-DD`, a datetime `YYYY-MM-DD hh:mm:ss` and a time `[-]hh:mm:ss`, each with
 * a point and fractional digits when the domain has decimals. A string is read with any
 * punctuation between the parts, as `YYYYMMDDhhmmss` digits, or with a two-digit year (70-99 are
 * 1970-1999, 00-69 are 2000-2069); a year of other than two digits is read as written. Fractional
 * seconds beyond six digits round half up at the seventh.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility MySqlMemory
 */
final class Temporal
{
    /**
     * Reads a date or datetime string into its parts, or null when it is not one.
     *
     * @return array{int, int, int, int, int, int, int, bool}|null Year, month, day, hour, minute, second, microsecond, and whether a time part was present
     */
    public static function parseDateTime(string $text): ?array
    {
        $text = trim($text, " \t\n\r");
        if (preg_match('/\A([0-9]{2}|[0-9]{4})([0-9]{2})([0-9]{2})(?:T?([0-9]{2})([0-9]{2})([0-9]{2})?)?(?:\.([0-9]*))?\z/', $text, $digits) === 1 && strlen($text) >= 6) {
            $parts = $digits;
        } elseif (preg_match('/\A([0-9]{1,4})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{1,2})(?:(?:[T ]|[^0-9]+)([0-9]{1,2})(?:[^0-9]+([0-9]{1,2})(?:[^0-9]+([0-9]{1,2}))?)?)?(?:\.([0-9]*))?\z/', $text, $delimited) === 1) {
            $parts = $delimited;
        } else {
            return null;
        }
        $year = (int) $parts[1];
        if (strlen($parts[1]) === 2) {
            $year += $year < 70 ? 2000 : 1900;
        }
        $fraction = substr(str_pad($parts[7] ?? '', 6, '0'), 0, 6);

        return [$year, (int) $parts[2], (int) $parts[3], (int) ($parts[4] ?? 0), (int) ($parts[5] ?? 0), (int) ($parts[6] ?? 0), (int) $fraction, ($parts[4] ?? '') !== ''];
    }

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
     * Reads a time string into its parts, or null when it is not one.
     *
     * @return array{bool, int, int, int, int}|null Negative, hours, minute, second and microsecond
     */
    public static function parseTime(string $text): ?array
    {
        $text = trim($text, " \t\n\r");
        if (preg_match('/\A(-)?(?:([0-9]+) +)?([0-9]+):([0-9]{1,2})(?::([0-9]{1,2}))?(?:\.([0-9]*))?\z/', $text, $match) === 1) {
            $hours = (int) ($match[2] === '' ? 0 : $match[2]) * 24 + (int) $match[3];

            return [$match[1] === '-', $hours, (int) $match[4], (int) ($match[5] ?? 0), (int) substr(str_pad($match[6] ?? '', 6, '0'), 0, 6)];
        }
        if (preg_match('/\A(-)?([0-9]+)(?:\.([0-9]*))?\z/', $text, $match) === 1) {
            $number = str_pad($match[2], 6, '0', STR_PAD_LEFT);

            return [$match[1] === '-', (int) substr($number, 0, -4), (int) substr($number, -4, 2), (int) substr($number, -2), (int) substr(str_pad($match[3] ?? '', 6, '0'), 0, 6)];
        }

        return null;
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
     * Rounds microseconds half up to a number of decimals, or truncates them; 1000000 is a whole second.
     */
    public static function scale(int $micro, int $decimals, bool $truncate): int
    {
        $unit = 10 ** (6 - $decimals);

        return ($truncate ? intdiv($micro, $unit) : intdiv($micro + intdiv($unit, 2), $unit)) * $unit;
    }

    /**
     * Tells whether parts name a date a mode accepts: one that exists, the zero date unless
     * NO_ZERO_DATE is set, or a date with a zero month or day unless NO_ZERO_IN_DATE is set.
     */
    public static function accepted(int $year, int $month, int $day, bool $noZeroDate, bool $noZeroInDate): bool
    {
        if ($year === 0 && $month === 0 && $day === 0) {
            return !$noZeroDate;
        }
        if ($month === 0 || $day === 0) {
            return !$noZeroInDate && $month <= 12 && $day <= 31 && $year <= 9999;
        }

        return self::valid($year, $month, $day);
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
     */
    public static function literal(string $form, string $text, int $decimals, bool $noZeroDate, bool $noZeroInDate): ?string
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
        if ($parts === null || $parts[8] !== '' || $parts[7] !== ($form === 'DATETIME') || !self::accepted($parts[0], $parts[1], $parts[2], $noZeroDate, $noZeroInDate)) {
            return null;
        }
        if ($form === 'DATE') {
            return self::date($parts[0], $parts[1], $parts[2]);
        }
        if ($parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return null;
        }
        $moment = self::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], self::micro($parts[6], false));

        return $moment === null ? null : self::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $decimals);
    }

    /**
     * Answers the datetime a time names on the day of an instant: the time added to its midnight, or subtracted when negative.
     *
     * @return array{int, int, int, int, int, int, int}|null The parts, or null outside years 0 to 9999
     */
    public static function onDay(float $instant, bool $negative, int $hours, int $minute, int $second, int $micro): ?array
    {
        $today = getdate((int) $instant);
        $delta = (($hours * 3600 + $minute * 60 + $second) * 1000000 + $micro) * ($negative ? -1 : 1);

        return Calendar::addMicroseconds($today['year'], $today['mon'], $today['mday'], 0, 0, 0, 0, $delta);
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
        $length = Calendar::monthLength($year, $month);
        [$year, $month, $day] = $day < $length ? [$year, $month, $day + 1] : ($month < 12 ? [$year, $month + 1, 1] : [$year + 1, 1, 1]);

        return [$year, $month, $day, 0, 0, 0, 0];
    }

    /**
     * Answers the number a date, datetime or time text reads as in a numeric context: YYYYMMDD, YYYYMMDDhhmmss or hhmmss, with the fraction.
     */
    public static function number(string $text): string
    {
        $negative = str_starts_with($text, '-');
        $digits = preg_replace('/[^0-9.]/', '', $text) ?? '0';

        return Decimal::canonical(($negative ? '-' : '') . $digits);
    }
}
