<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Interval;
use MySqlMemory\Value\Real;
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * A date or time moved by an interval: `+ INTERVAL`, `- INTERVAL`, DATE_ADD, DATE_SUB, ADDDATE and SUBDATE.
 *
 * Moving by months keeps the day within the month reached. A value that is no date, or a
 * result outside years 1000 to 9999, is NULL with a warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add.
 *
 * @visibility MySqlMemory
 */
final class DateShift implements Evaluable
{
    /**
     * @param Evaluable $operand The date or time moved
     * @param Evaluable $quantity The quantity of the interval
     * @param IntervalUnit $unit The unit of the interval
     * @param bool $subtract Whether the interval is subtracted
     * @param Domain $domain The domain of the result
     */
    public function __construct(public readonly Evaluable $operand, public readonly Evaluable $quantity, public readonly IntervalUnit $unit, public readonly bool $subtract, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Moves the value for a row.
     *
     * The value is read first: when it is NULL, or no date, the interval is not read at all.
     */
    #[Override]
    public function evaluate(Frame $frame): ?string
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($this->domain->kind === Kind::Time) {
            $interval = $this->interval($frame);

            return $interval === null ? null : $this->time((string) $value, $this->subtract ? -$interval->microseconds : $interval->microseconds);
        }
        $parts = $this->moment($value, $frame);
        if ($parts === null) {
            return null;
        }
        $interval = $this->interval($frame);
        if ($interval === null) {
            return null;
        }
        $months = $this->subtract ? -$interval->months : $interval->months;
        $micro = $this->subtract ? -$interval->microseconds : $interval->microseconds;
        [$year, $month, $day, $hour, $minute, $second, $fraction, $timed] = $parts;
        if ($months !== 0) {
            $moved = Calendar::addMonths($year, $month, $day, $months);
            if ($moved === null) {
                $frame->context->warning(DataError::DatetimeFunctionOverflow, 'datetime');

                return null;
            }
            [$year, $month, $day] = $moved;
        }
        $moved = Calendar::addMicroseconds($year, $month, $day, $hour, $minute, $second, $fraction, $micro);
        if ($moved === null) {
            $frame->context->warning(DataError::DatetimeFunctionOverflow, 'datetime');

            return null;
        }

        return $this->write($moved, $timed || $fraction !== 0 || !Interval::dated($this->unit), $fraction !== 0 || $moved[6] !== 0);
    }

    /**
     * Reads the value moved as the parts of a datetime, or answers null with a warning when it is no date.
     *
     * A TIME is the time on the day the statement started. A date followed by more text is read with a warning (ER_TRUNCATED_WRONG_VALUE); anything
     * else that is no date, a date with a zero month or day included, warns that it is an
     * incorrect datetime value. The warning quotes a string with the bytes of a binary one
     * escaped, an integer as a signed one, and a double as the server writes it.
     *
     * @return array{int, int, int, int, int, int, int, bool}|null
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function moment(int|float|string $value, Frame $frame): ?array
    {
        $domain = $this->operand->domain();
        if ($domain->kind === Kind::Time) {
            $moment = (new Moments())->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $frame->context);
            $parts = $moment === null ? null : Temporal::parseDateTime($moment);

            return $parts === null ? null : [$parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], true];
        }
        $textual = $domain->kind === Kind::String || $domain->kind->temporal();
        $text = $textual ? (string) Convert::toText($value, $domain) : (string) Convert::toDecimal($value, $domain, $frame->context);
        $shown = $this->shown($value, $domain, $text);
        $parts = Temporal::parseDateTime($text);
        $scanned = $parts === null ? Temporal::scanDateTime($text) : null;
        if ($scanned !== null && $scanned[8] !== '' && Temporal::valid($scanned[0], $scanned[1], $scanned[2]) && $scanned[1] !== 0 && $scanned[2] !== 0) {
            $frame->context->warning(DataError::TruncatedWrongValue, $scanned[7] ? 'datetime' : 'date', $shown);

            return [$scanned[0], $scanned[1], $scanned[2], $scanned[3], $scanned[4], $scanned[5], (int) substr(str_pad($scanned[6], 6, '0'), 0, 6), $scanned[7]];
        }
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[1] === 0 || $parts[2] === 0) {
            $frame->context->warnMessage(DataError::TruncatedWrongValue, DataError::WrongValue->message('datetime', $shown));

            return null;
        }

        return $parts;
    }

    /**
     * Answers how a warning quotes a value that is no date: a string or temporal value as its text with the bytes of a binary one escaped, an integer as a signed one, and a double as the server writes it.
     */
    public function shown(int|float|string $value, Domain $domain, string $text): string
    {
        return match (true) {
            $domain->kind === Kind::String || $domain->kind->temporal() => Convert::shown($text, Convert::readableCharset($domain)),
            $domain->kind === Kind::Integer => (string) (int) $value,
            $domain->kind === Kind::Double => Real::format((float) $value),
            default => $text,
        };
    }

    /**
     * Reads the interval for a row, or answers null when its quantity is NULL or overflows its unit.
     *
     * The quantity of SECOND is read as a decimal, of another single unit as an integer, and of a
     * compound unit as text; a text with more numbers than the unit has parts warns
     * (ER_DATETIME_FUNCTION_OVERFLOW).
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function interval(Frame $frame): ?Interval
    {
        $quantity = $this->quantity->evaluate($frame);
        if ($quantity === null) {
            return null;
        }
        $domain = $this->quantity->domain();
        $text = match ($this->unit) {
            IntervalUnit::Second => (string) Convert::toDecimal($quantity, $domain, $frame->context),
            IntervalUnit::Microsecond, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year => Integer::text((int) Convert::toInteger($quantity, $domain, $frame->context), $domain->unsigned && $domain->kind === Kind::Integer),
            IntervalUnit::YearMonth, IntervalUnit::DayHour, IntervalUnit::DayMinute, IntervalUnit::DaySecond, IntervalUnit::DayMicrosecond, IntervalUnit::HourMinute, IntervalUnit::HourSecond, IntervalUnit::HourMicrosecond, IntervalUnit::MinuteSecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::SecondMicrosecond => (string) Convert::toText($quantity, $domain),
        };

        $interval = Interval::read($text, $this->unit);
        if ($interval === null) {
            $frame->context->warning(DataError::DatetimeFunctionOverflow, 'date_add_interval');
        }

        return $interval;
    }

    /**
     * Writes the moved date or datetime in the kind of the result.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function write(array $parts, bool $timed, bool $fractional): string
    {
        [$year, $month, $day, $hour, $minute, $second, $micro] = $parts;
        if ($this->domain->kind === Kind::Date || ($this->domain->kind === Kind::String && !$timed)) {
            return Temporal::date($year, $month, $day);
        }
        $decimals = $this->domain->kind === Kind::String ? ($fractional ? 6 : 0) : $this->domain->decimals;

        return Temporal::dateTime($year, $month, $day, $hour, $minute, $second, $micro, $decimals);
    }

    /**
     * Moves a time by microseconds.
     */
    public function time(string $value, int $micro): ?string
    {
        $parts = Temporal::parseTime($value);
        if ($parts === null) {
            return null;
        }
        $total = (($parts[1] * 3600 + $parts[2] * 60 + $parts[3]) * 1000000 + $parts[4]) * ($parts[0] ? -1 : 1) + $micro;
        $negative = $total < 0;
        $total = abs($total);
        $seconds = intdiv($total, 1000000);

        return Temporal::time($negative, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $total % 1000000, $this->domain->decimals);
    }
}
