<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Reads and writes dates, times and datetimes in the canonical text the emulator holds them in.
 *
 * A date is `YYYY-MM-DD`, a datetime `YYYY-MM-DD hh:mm:ss` and a time `[-]hh:mm:ss`, each with
 * a point and fractional digits when the domain has decimals. A string is read with any
 * punctuation between the parts, as `YYYYMMDDhhmmss` digits, or with a two-digit year (70-99 are
 * 1970-1999, 00-69 are 2000-2069).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
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
        if (strlen($parts[1]) <= 2) {
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
     * Answers the number a date, datetime or time text reads as in a numeric context: YYYYMMDD, YYYYMMDDhhmmss or hhmmss, with the fraction.
     */
    public static function number(string $text): string
    {
        $negative = str_starts_with($text, '-');
        $digits = preg_replace('/[^0-9.]/', '', $text) ?? '0';

        return Decimal::canonical(($negative ? '-' : '') . $digits);
    }
}
