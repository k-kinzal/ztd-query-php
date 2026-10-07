<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Converts values to dates, times, datetimes and years.
 *
 * A string is read as a date or time text; a number as YYYYMMDD, YYYYMMDDhhmmss or hhmmss
 * digits. A value that is no valid date or time converts to NULL with a warning
 * (ER_TRUNCATED_WRONG_VALUE).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-conversion.html.
 *
 * @visibility MySqlMemory
 */
final class Moments
{
    /**
     * Converts a value to the temporal kind of a domain, or null with a warning.
     */
    public function convert(int|float|string $value, Domain $from, Domain $to, Context $context): ?string
    {
        if ($to->kind === Kind::Time) {
            return $this->time($value, $from, $to->decimals, $context);
        }
        $text = $from->kind->temporal() || $from->kind === Kind::String ? (string) $value : $this->digits($value, $from, $context);
        if ($from->kind === Kind::Time) {
            $time = Temporal::parseTime($text);
            $today = getdate((int) $context->started);
            $text = $time === null ? '' : sprintf('%04d-%02d-%02d %02d:%02d:%02d.%06d', $today['year'], $today['mon'], $today['mday'], $time[1], $time[2], $time[3], $time[4]);
        }
        $parts = Temporal::parseDateTime($text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            $context->warning(ErrorCode::TruncatedWrongValue, $to->kind === Kind::Date ? 'date' : 'datetime', (string) Convert::toText($value, $from));

            return null;
        }
        if ($to->kind === Kind::Date) {
            return Temporal::date($parts[0], $parts[1], $parts[2]);
        }

        return Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $this->rounded($parts[6], $to->decimals), $to->decimals);
    }

    /**
     * Converts a value to a time, or null with a warning.
     */
    public function time(int|float|string $value, Domain $from, int $decimals, Context $context): ?string
    {
        if ($from->kind === Kind::Date || $from->kind === Kind::DateTime) {
            $parts = Temporal::parseDateTime((string) $value);

            return $parts === null ? null : Temporal::time(false, $parts[3], $parts[4], $parts[5], $this->rounded($parts[6], $decimals), $decimals);
        }
        $text = $from->kind === Kind::String || $from->kind === Kind::Time ? (string) $value : $this->digits($value, $from, $context);
        $parts = Temporal::parseTime($text);
        if ($parts === null || $parts[2] > 59 || $parts[3] > 59) {
            $context->warning(ErrorCode::TruncatedWrongValue, 'time', (string) Convert::toText($value, $from));

            return null;
        }
        if ($parts[1] > 838) {
            return Temporal::time($parts[0], 838, 59, 59, 0, $decimals);
        }

        return Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $this->rounded($parts[4], $decimals), $decimals);
    }

    /**
     * Converts a value to a year: 0, or 1901 to 2155.
     */
    public function year(int|float|string $value, Domain $from, Context $context): ?int
    {
        $year = (int) Convert::toInteger($value, $from, $context);
        if ($from->kind !== Kind::String && $year >= 1 && $year <= 69) {
            return $year + 2000;
        }
        if ($from->kind !== Kind::String && $year >= 70 && $year <= 99) {
            return $year + 1900;
        }

        return $year === 0 || ($year >= 1901 && $year <= 2155) ? $year : null;
    }

    /**
     * Writes a number as the digits it is read as a date from.
     */
    public function digits(int|float|string $value, Domain $from, Context $context): string
    {
        return (string) Convert::toDecimal($value, $from, $context);
    }

    /**
     * Truncates microseconds to a number of decimals.
     */
    public function rounded(int $micro, int $decimals): int
    {
        $unit = 10 ** (6 - $decimals);

        return intdiv($micro, $unit) * $unit;
    }
}
