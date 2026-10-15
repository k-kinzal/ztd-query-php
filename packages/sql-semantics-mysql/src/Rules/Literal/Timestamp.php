<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Literal;

/**
 * Reads the representation of a TIMESTAMP literal independently of session modes.
 *
 * The decoded operand contains a date and a clock, optionally followed by a
 * signed displacement. Calendar-invalid days and zero dates remain mode-dependent.
 * Compact and relaxed representations are based on native SQL observations on
 * MySQL 5.6, 5.7, 8.0, 8.4 and 9.1; no engine implementation is used.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Timestamp
{
    /**
     * Reads all components, retaining fractional digits and a displacement in seconds.
     *
     * @return array{int, int, int, int, int, int, string, int|null}|null
     */
    public static function parts(string $text): ?array
    {
        $text = trim($text, " \t\n\r\v\f");
        $offset = null;
        $parts = self::compact($text) ?? self::delimited($text);
        if ($parts === null && preg_match('/([+-])([0-9]{2}):([0-9]{2})\z/', $text, $zone) === 1) {
            $minutes = (int) $zone[2] * 60 + (int) $zone[3];
            if ((int) $zone[3] > 59 || $minutes > 840 || ($minutes === 0 && $zone[1] === '-')) {
                return null;
            }
            $offset = $minutes * 60 * ($zone[1] === '-' ? -1 : 1);
            $prefix = substr($text, 0, -6);
            $parts = self::delimited($prefix, true) ?? (str_contains($prefix, '.') ? self::compact($prefix, true) : null);
        }
        if ($parts === null) {
            return null;
        }
        $year = (int) $parts[0];
        if (strlen($parts[0]) === 2 && implode('', $parts) !== str_repeat('0', strlen(implode('', $parts)))) {
            $year += $year < 70 ? 2000 : 1900;
        }

        return [$year, (int) $parts[1], (int) $parts[2], (int) $parts[3], (int) $parts[4], (int) $parts[5], $parts[6], $offset];
    }

    /**
     * Reads compact date and clock fields, including shortened terminal clock fields.
     *
     * @return array{string, string, string, string, string, string, string}|null
     */
    public static function compact(string $text, bool $complete = false): ?array
    {
        if (preg_match('/\A([0-9]+T?[0-9]*)(?:\.([0-9]*))?\z/', $text, $match) !== 1) {
            return null;
        }
        $width = strlen($match[1]) >= 14 || strlen($match[1]) === 8 ? 4 : 2;
        if (preg_match('/\A([0-9]{' . $width . '})([0-9]{2})([0-9]{2})T?([0-9]{1,2})([0-9]{1,2})?([0-9]{1,2})?\z/', $match[1], $fields) !== 1 || ($complete && !isset($fields[6]))) {
            return null;
        }

        return [$fields[1], $fields[2], $fields[3], $fields[4], $fields[5] ?? '0', $fields[6] ?? '0', $match[2] ?? ''];
    }

    /**
     * Reads punctuation-delimited fields; spaces are allowed only at the date/clock boundary.
     *
     * @return array{string, string, string, string, string, string, string}|null
     */
    public static function delimited(string $text, bool $complete = false): ?array
    {
        $separator = '[[:punct:]]+';
        $boundary = '(?:T|[[:space:][:punct:]]+)';
        if (preg_match('/\A[0-9]+\./', $text) === 1) {
            $separator = '[[:punct:]]*';
            $boundary = '(?:T|[[:space:][:punct:]]*)';
        }
        if (preg_match('/\A([0-9]{1,4})' . $separator . '([0-9]{1,2})' . $separator . '([0-9]{1,2})' . $boundary . '([0-9]{1,2})(?:' . $separator . '([0-9]{1,2})(?:' . $separator . '([0-9]{1,2}))?)?(?:\.([0-9]*))?\z/', $text, $parts) !== 1 || ($complete && !isset($parts[6]))) {
            return null;
        }

        return [$parts[1], $parts[2], $parts[3], $parts[4], $parts[5] ?? '0', $parts[6] ?? '0', $parts[7] ?? ''];
    }

    /**
     * Tells whether the representation has bounded fields and can survive fractional rounding.
     */
    public static function valid(string $text): bool
    {
        $parts = self::parts($text);
        if ($parts !== null && $parts[7] !== null && ($parts[1] === 0 || $parts[2] === 0) && Temporal::micro($parts[6], false) < 1000000) {
            return false;
        }

        return self::value($text, 6, false, false, true) !== null;
    }

    /**
     * Applies calendar modes and rounds the local fields, before any displacement conversion.
     */
    public static function value(string $text, int $decimals, bool $noZeroDate, bool $noZeroInDate, bool $allowInvalidDates): ?string
    {
        $parts = self::parts($text);
        if ($parts === null || !Temporal::accepted($parts[0], $parts[1], $parts[2], $noZeroDate, $noZeroInDate, $allowInvalidDates) || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return null;
        }
        $micro = Temporal::micro($parts[6], false);
        if ($micro >= 1000000 && ($parts[1] === 0 || $parts[2] === 0 || !Temporal::valid($parts[0], $parts[1], $parts[2]))) {
            return null;
        }
        $moment = Temporal::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $micro);

        return $moment === null ? null : Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $decimals);
    }
}
