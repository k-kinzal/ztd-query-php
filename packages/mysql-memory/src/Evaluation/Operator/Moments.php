<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Converts values to dates, times, datetimes and years.
 *
 * A string is read as a date or time text; a number as YYYYMMDD, YYYYMMDDhhmmss or hhmmss
 * digits. A value that is no valid date or time converts to NULL with a warning
 * (ER_TRUNCATED_WRONG_VALUE); a value followed by more text converts with a warning. Fractional
 * seconds are rounded half up to the precision of the target, first to six digits, or truncated
 * under TIME_TRUNCATE_FRACTIONAL; a datetime rounded past 9999-12-31 is NULL with a warning
 * (ER_DATETIME_FUNCTION_OVERFLOW); a number that writes no time of day drops its fraction. In a time context a datetime gives its time, and a time beyond
 * 838:59:59 is clamped with a warning. A one- or two-digit year is in 2000-2069 or 1970-1999;
 * a string with no year (ER_WRONG_VALUE) or a year outside 1901-2155 converts to NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-conversion.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html,
 * https://dev.mysql.com/doc/refman/8.4/en/year.html.
 *
 * @visibility MySqlMemory
 */
final class Moments
{
    /**
     * Converts a value to the temporal kind of a domain, or null with a warning.
     *
     * @param array{bool, bool}|null $zeros Whether the zero date and a zero month or day are refused; as NO_ZERO_DATE and NO_ZERO_IN_DATE say when null
     */
    public function convert(int|float|string $value, Domain $from, Domain $to, Context $context, ?array $zeros = null): ?string
    {
        if ($to->kind === Kind::Time) {
            return $this->time($value, $from, $to->decimals, $context);
        }
        $truncate = $context->modes->has('TIME_TRUNCATE_FRACTIONAL');
        $shown = (string) Convert::toText($value, $from);
        $parts = $this->parts($value, $from, $context, $zeros, $shown);
        if ($parts === null) {
            return null;
        }
        if ($to->kind === Kind::Date) {
            $date = Temporal::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], intdiv($parts[6], 1000000) * 1000000);
            if ($date === null) {
                $context->warning(DataError::DatetimeFunctionOverflow, 'datetime');

                return null;
            }
            $result = Temporal::date($date[0], $date[1], $date[2]);
        } else {
            $moment = Temporal::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], Temporal::scale($parts[6] % 1000000, $to->decimals, $truncate) + intdiv($parts[6], 1000000) * 1000000);
            if ($moment === null) {
                $context->warning(DataError::DatetimeFunctionOverflow, 'datetime');

                return null;
            }
            $result = Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $to->decimals);
        }
        if ($parts[8] !== '') {
            $context->warning(DataError::TruncatedWrongValue, $parts[7] ? 'datetime' : 'date', $shown);
        }

        return $result;
    }

    /**
     * Reads a value as the parts of a datetime, with whether it has a time part and the text after it, or answers null with a warning when it is none.
     *
     * A TIME is the time on the day the statement started; a number is read as its date digits.
     *
     * @param array{bool, bool}|null $zeros Whether the zero date and a zero month or day are refused; as NO_ZERO_DATE and NO_ZERO_IN_DATE say when null
     * @return array{int, int, int, int, int, int, int, bool, string}|null
     */
    public function parts(int|float|string $value, Domain $from, Context $context, ?array $zeros, string $shown): ?array
    {
        $truncate = $context->modes->has('TIME_TRUNCATE_FRACTIONAL');
        if ($from->kind === Kind::Time) {
            $time = Temporal::scanTime((string) $value);
            $moment = $time === null ? null : Temporal::onDay($context->zone()->local((int) floor($context->started)), $time[0], $time[1], $time[2], $time[3], Temporal::micro($time[4], $truncate));

            return $moment === null ? null : [...$moment, true, ''];
        }
        $scanned = Temporal::scanDateTime($from->kind->temporal() || $from->kind === Kind::String ? (string) $value : $this->padded($this->digits($value, $from, $context)));
        $modes = $context->modes;
        if ($scanned === null || !Temporal::accepted($scanned[0], $scanned[1], $scanned[2], $zeros[0] ?? $modes->has('NO_ZERO_DATE'), $zeros[1] ?? $modes->has('NO_ZERO_IN_DATE')) || $scanned[3] > 23 || $scanned[4] > 59 || $scanned[5] > 59) {
            $context->warnMessage(DataError::TruncatedWrongValue, DataError::WrongValue->message('datetime', $shown));

            return null;
        }

        return [$scanned[0], $scanned[1], $scanned[2], $scanned[3], $scanned[4], $scanned[5], $scanned[7] || $from->kind === Kind::String ? Temporal::micro($scanned[6], $truncate) : 0, $scanned[7], $scanned[8]];
    }

    /**
     * Converts a value to a time, or null with a warning.
     *
     * A string that holds a datetime gives its time. A number beyond 838:59:59 converts to NULL,
     * a string to 838:59:59.
     */
    public function time(int|float|string $value, Domain $from, int $decimals, Context $context): ?string
    {
        $truncate = $context->modes->has('TIME_TRUNCATE_FRACTIONAL');
        $shown = (string) Convert::toText($value, $from);
        $numeric = !$from->kind->temporal() && $from->kind !== Kind::String;
        $time = $this->clock($value, $from, $numeric, $shown, $context);
        if ($time === null) {
            return null;
        }
        [$negative, $hours, $minute, $second, $digits, $rest] = $time;
        $micro = Temporal::micro($digits, $truncate);
        if ($hours > 838 || ($hours === 838 && $minute === 59 && $second === 59 && $micro > 0)) {
            if ($numeric && $hours > 838) {
                $context->warning(DataError::TruncatedWrongValue, 'time', $shown);

                return null;
            }
            if (!$numeric) {
                $context->warning(DataError::TruncatedWrongValue, 'time', $shown);
            }

            return Temporal::time($negative, 838, 59, 59, 0, $decimals);
        }
        if ($rest !== '') {
            $context->warning(DataError::TruncatedWrongValue, 'time', $shown);
        }
        $micro = Temporal::scale($micro % 1000000, $decimals, $truncate) + intdiv($micro, 1000000) * 1000000;
        $seconds = $hours * 3600 + $minute * 60 + $second + intdiv($micro, 1000000);

        return Temporal::time($negative, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $micro % 1000000, $decimals);
    }

    /**
     * Reads the time of a value before it is clamped and rounded, or null with a warning: the time of a datetime, else the time the text or digits write.
     *
     * @param bool $numeric Whether the value is a number, read as the digits of its decimal value
     * @param string $shown The value as the warning shows it
     * @return array{bool, int, int, int, string, string}|null Negative, hours, minute, second, fractional digits, and the text after the value
     */
    public function clock(int|float|string $value, Domain $from, bool $numeric, string $shown, Context $context): ?array
    {
        $text = $numeric ? $this->digits($value, $from, $context) : (string) $value;
        $moment = $from->kind === Kind::Time ? null : Temporal::scanDateTime($text, $from->kind !== Kind::Date && $from->kind !== Kind::DateTime);
        if ($moment !== null && ($moment[7] || $from->kind->temporal())) {
            if (!Temporal::accepted($moment[0], $moment[1], $moment[2], false, false) || $moment[3] > 23 || $moment[4] > 59 || $moment[5] > 59) {
                $context->warning(DataError::TruncatedWrongValue, 'time', $shown);

                return null;
            }

            return [false, $moment[3], $moment[4], $moment[5], $moment[6], $moment[8]];
        }
        $time = Temporal::scanTime($text);
        if ($time === null || $time[2] > 59 || $time[3] > 59) {
            $context->warning(DataError::TruncatedWrongValue, 'time', $shown);

            return null;
        }

        return $time;
    }

    /**
     * Converts a value to a year: 0, or 1901 to 2155, or null with a warning.
     *
     * A string is read up to its first character that is not a digit; a number is rounded. One
     * and two digits name 2000-2069 and 1970-1999, and so does the string zero; the number zero is
     * the zero year.
     */
    public function year(int|float|string $value, Domain $from, Context $context): ?int
    {
        if ($from->kind === Kind::Date || $from->kind === Kind::DateTime) {
            return (int) substr((string) $value, 0, 4);
        }
        if ($from->kind === Kind::Time) {
            return $context->local()[0];
        }
        if ($from->kind === Kind::String) {
            $text = (string) $value;
            if (preg_match('/\A[ \t\n\r\v\f]*\+?([0-9]+)/', $text, $match) !== 1) {
                $context->warning(DataError::WrongValue, 'YEAR', $text);

                return null;
            }
            if (strlen($match[0]) < strlen($text)) {
                $context->warning(DataError::TruncatedWrongValue, 'YEAR', $text);
            }
            $number = Decimal::canonical($match[1]);
            if (Decimal::compare($number, '99') <= 0) {
                $year = (int) $number;

                return $year < 70 ? $year + 2000 : $year + 1900;
            }
        } else {
            $number = (string) Convert::toInteger($value, $from, $context);
            if ($number === '0') {
                return 0;
            }
            if (Decimal::compare($number, '0') > 0 && Decimal::compare($number, '99') <= 0) {
                $year = (int) $number;

                return $year < 70 ? $year + 2000 : $year + 1900;
            }
        }
        if (Decimal::compare($number, '1901') < 0 || Decimal::compare($number, '2155') > 0) {
            $context->warning(DataError::TruncatedWrongValue, 'YEAR', $number);

            return null;
        }

        return (int) $number;
    }

    /**
     * Writes a number as the digits it is read as a date from.
     */
    public function digits(int|float|string $value, Domain $from, Context $context): string
    {
        return (string) Convert::toDecimal($value, $from, $context);
    }

    /**
     * Pads the digits of a number to the length of a date or datetime it is read as: YYMMDD up to six digits, YYYYMMDD up to eight, then YYMMDDhhmmss or YYYYMMDDhhmmss.
     *
     * Zero is the zero date; a number below 101 or a negative number is left as it is, no date
     * (verified on a live 8.4 server).
     */
    public function padded(string $digits): string
    {
        $parts = explode('.', $digits, 2);
        $whole = $parts[0];
        if ($whole === '0') {
            return '00000000';
        }
        if (str_starts_with($whole, '-') || strlen($whole) > 14 || (int) $whole < 101) {
            return $digits;
        }
        foreach ([6, 8, 12, 14] as $length) {
            if (strlen($whole) <= $length) {
                return str_pad($whole, $length, '0', STR_PAD_LEFT) . (isset($parts[1]) ? '.' . $parts[1] : '');
            }
        }

        return $digits;
    }
}
