<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions of times of day and durations: TIME, TIMESTAMP, ADDTIME, SUBTIME, TIMEDIFF, MAKETIME, SEC_TO_TIME and TIME_TO_SEC.
 *
 * A time is at most 838:59:59 either way; a result beyond it is clamped with a warning that shows
 * the value reached. ADDTIME and SUBTIME add to a date and time when the first argument is one
 * (a DATE, a DATETIME, or a string or number that writes a time of day after a date) and to a
 * time otherwise; the second argument must be a time. TIMEDIFF subtracts two values of the same
 * kind, reading strings as times unless the first argument is a DATE or DATETIME, and is NULL
 * for two kinds. A date and time beyond 9999-12-31 is NULL with ER_DATETIME_FUNCTION_OVERFLOW
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Clocks
{
    /**
     * The longest duration of a TIME, in microseconds.
     */
    public const LONGEST = 3020399000000;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('TIME', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->time($f, $a[0], $r)),
            new Routine('TIMESTAMP', 1, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->timestamp($f, $a, $r)),
            new Routine('ADDTIME', 2, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->add($f, $a[0], $a[1], $r, false)),
            new Routine('SUBTIME', 2, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->add($f, $a[0], $a[1], $r, true)),
            new Routine('TIMEDIFF', 2, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->difference($f, $a[0], $a[1], $r)),
            new Routine('MAKETIME', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->make($f, $a, $r)),
            new Routine('SEC_TO_TIME', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->fromSeconds($f, $a[0], $r)),
            new Routine('TIME_TO_SEC', 1, 1, fn (Frame $f, array $a): ?int => $this->toSeconds($f, $a[0])),
        ];
    }

    /**
     * TIME: the time of a value.
     */
    public function time(Frame $frame, Evaluable $argument, Domain $result): ?string
    {
        $value = $argument->evaluate($frame);

        return $value === null ? null : (new Moments())->time($value, $argument->domain(), $result->decimals, $frame->context);
    }

    /**
     * TIMESTAMP: a date and time, with a time added when a second argument is given.
     *
     * @param list<Evaluable> $arguments
     */
    public function timestamp(Frame $frame, array $arguments, Domain $result): ?string
    {
        $readings = new Readings();
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if (!isset($arguments[1])) {
            return (new Moments())->convert($value, $arguments[0]->domain(), $result, $frame->context);
        }
        $parts = $readings->value($value, $arguments[0]->domain(), Zeros::Modes, $frame->context);
        if ($parts === null) {
            return null;
        }
        $second = $arguments[1]->evaluate($frame);
        if ($second === null || ($arguments[1]->domain()->kind === Kind::String && $this->dated($second, $arguments[1]->domain(), false))) {
            return null;
        }
        $time = $this->duration($second, $arguments[1]->domain(), $frame->context);

        return $time === null ? null : $this->moved($parts, $time, $result, $frame->context);
    }

    /**
     * ADDTIME and SUBTIME: a date and time or a time moved by a time.
     */
    public function add(Frame $frame, Evaluable $left, Evaluable $right, Domain $result, bool $subtract): ?string
    {
        $value = $left->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $amount = $right->evaluate($frame);
        $domain = $right->domain();
        if ($amount === null || $domain->kind === Kind::Date || $domain->kind === Kind::DateTime || $this->dated($amount, $domain, false)) {
            return null;
        }
        $context = $frame->context;
        $from = $left->domain();
        $dated = $from->kind === Kind::Date || $from->kind === Kind::DateTime || ($from->kind !== Kind::Time && $this->dated($value, $from, true));
        $first = $dated ? (new Readings())->value($value, $from, Zeros::Modes, $context) : null;
        if ($dated && $first === null) {
            return null;
        }
        $time = $this->duration($amount, $domain, $context);
        if ($time === null) {
            return null;
        }
        $time = $subtract ? -$time : $time;
        if ($first !== null) {
            return $this->moved($first, $time, $result, $context);
        }
        $start = $this->duration($value, $from, $context);

        return $start === null ? null : $this->clamped($start + $time, $result, $context);
    }

    /**
     * TIMEDIFF: the time from the second value to the first.
     */
    public function difference(Frame $frame, Evaluable $left, Evaluable $right, Domain $result): ?string
    {
        $first = $left->evaluate($frame);
        if ($first === null) {
            return null;
        }
        $second = $right->evaluate($frame);
        if ($second === null) {
            return null;
        }
        $dated = $left->domain()->kind === Kind::Date || $left->domain()->kind === Kind::DateTime;
        $from = $this->instant($first, $left->domain(), $dated, $frame->context);
        $to = $from === null ? null : $this->instant($second, $right->domain(), $dated, $frame->context);
        if ($from === null || $to === null || $from[0] !== $to[0]) {
            return null;
        }

        return $this->clamped($from[1] - $to[1], $result, $frame->context);
    }

    /**
     * Reads a value for TIMEDIFF: its kind (date, datetime or time) and its microseconds, from 1970 for a date or datetime.
     *
     * @param bool $dated Whether strings and numbers are read as dates and times
     * @return array{string, int}|null
     */
    public function instant(int|float|string $value, Domain $domain, bool $dated, Context $context): ?array
    {
        $kind = match ($domain->kind) {
            Kind::Date => 'date',
            Kind::DateTime => 'datetime',
            Kind::Time => 'time',
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => $dated ? ($this->dated($value, $domain, true) ? 'datetime' : 'date') : ($this->dated($value, $domain, true) ? 'datetime' : 'time'),
        };
        if ($kind === 'time') {
            $time = $this->duration($value, $domain, $context);

            return $time === null ? null : ['time', $time];
        }
        $parts = (new Readings())->value($value, $domain, Zeros::Modes, $context);

        return $parts === null ? null : [$kind, Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]) * 1000000 + $parts[6]];
    }

    /**
     * Tells whether a string or a number writes a time of day after a date.
     *
     * @param bool $time Whether a number needs twelve digits or more, as in a time context
     */
    public function dated(int|float|string $value, Domain $domain, bool $time): bool
    {
        if ($domain->kind === Kind::DateTime) {
            return true;
        }
        if ($domain->kind !== Kind::String && $domain->kind !== Kind::Integer && $domain->kind !== Kind::Decimal && $domain->kind !== Kind::Double) {
            return false;
        }
        $text = $domain->kind === Kind::String ? (string) $value : (string) Convert::toText($value, $domain);
        $scanned = Temporal::scanDateTime($text, true);

        return $scanned !== null && $scanned[7];
    }

    /**
     * Reads a value as a duration in microseconds, clamped to a TIME with a warning, or null.
     */
    public function duration(int|float|string $value, Domain $domain, Context $context): ?int
    {
        $time = (new Moments())->time($value, $domain, 6, $context);
        $parts = $time === null ? null : Temporal::parseTime($time);
        if ($parts === null) {
            return null;
        }

        return ((($parts[1] * 60 + $parts[2]) * 60 + $parts[3]) * 1000000 + $parts[4]) * ($parts[0] ? -1 : 1);
    }

    /**
     * Moves a date and time by microseconds and writes it in the domain of the result, or null past 9999 with a warning.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function moved(array $parts, int $micro, Domain $result, Context $context): ?string
    {
        $moment = Calendar::addMicroseconds($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $micro);
        if ($moment === null) {
            if ($micro > 0) {
                $context->warning(DataError::DatetimeFunctionOverflow, 'add_time');
            }

            return null;
        }
        $decimals = $result->kind === Kind::DateTime ? $result->decimals : ($moment[6] === 0 ? 0 : 6);

        return Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $decimals);
    }

    /**
     * Writes a duration in microseconds as a time in the domain of the result, clamped to 838:59:59 with a warning that shows the value reached.
     */
    public function clamped(int $micro, Domain $result, Context $context): string
    {
        $decimals = $result->kind === Kind::Time ? $result->decimals : (abs($micro) % 1000000 === 0 ? 0 : 6);
        if (abs($micro) > self::LONGEST) {
            $context->warning(DataError::TruncatedWrongValue, 'time', $this->written($micro, $decimals));
            $micro = $micro < 0 ? -self::LONGEST : self::LONGEST;
        }

        return $this->written($micro, $decimals);
    }

    /**
     * Writes a duration in microseconds as a time with a number of fractional digits; the hours are not bounded.
     */
    public function written(int $micro, int $decimals): string
    {
        $magnitude = abs($micro);
        $seconds = intdiv($magnitude, 1000000);

        return Temporal::time($micro < 0, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $magnitude % 1000000, $decimals);
    }

    /**
     * MAKETIME: the time of an hour, a minute and a second; NULL for a minute or second outside 0 to 59.
     *
     * @param list<Evaluable> $arguments
     */
    public function make(Frame $frame, array $arguments, Domain $result): ?string
    {
        $readings = new Readings();
        $hours = $readings->integer($frame, $arguments[0]);
        $minutes = $readings->integer($frame, $arguments[1]);
        $value = $arguments[2]->evaluate($frame);
        if ($hours === null || $minutes === null || $value === null) {
            return null;
        }
        $written = (string) Convert::toDecimal($value, $arguments[2]->domain(), $frame->context);
        if ($minutes < 0 || $minutes > 59 || Decimal::compare($written, '0') < 0 || Decimal::compare($written, '60') >= 0) {
            return null;
        }
        $seconds = Decimal::round($written, $result->decimals);
        $whole = (int) Decimal::truncate($seconds, 0);
        $micro = (int) Decimal::multiply(Decimal::subtract($seconds, (string) $whole), '1000000');
        if (abs($hours) > 838) {
            $context = $frame->context;
            $context->warning(DataError::TruncatedWrongValue, 'time', sprintf('%s%d:%02d:%02d', $hours < 0 ? '-' : '', abs($hours), $minutes, $whole));

            return Temporal::time($hours < 0, 838, 59, 59, 0, $result->decimals);
        }
        $total = ((abs($hours) * 60 + $minutes) * 60 + $whole) * 1000000 + $micro;

        return $this->clamped($hours < 0 ? -$total : $total, $result, $frame->context);
    }

    /**
     * SEC_TO_TIME: the time of a number of seconds.
     */
    public function fromSeconds(Frame $frame, Evaluable $argument, Domain $result): ?string
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $number = (string) Convert::toDecimal($value, $argument->domain(), $frame->context);
        $negative = str_starts_with($number, '-');
        $magnitude = ltrim($number, '-');
        if (Decimal::compare($magnitude, '3020399') > 0) {
            $frame->context->warning(DataError::TruncatedWrongValue, 'time', $argument->domain()->kind === Kind::Double ? Decimal::canonical(sprintf('%.0f', (float) $value)) : $number);

            return Temporal::time($negative, 838, 59, 59, 0, $result->decimals);
        }
        $rounded = Decimal::round($magnitude, $result->decimals);
        $whole = (int) Decimal::truncate($rounded, 0);
        $micro = (int) Decimal::multiply(Decimal::subtract($rounded, (string) $whole), '1000000');

        return Temporal::time($negative, intdiv($whole, 3600), intdiv($whole % 3600, 60), $whole % 60, $micro, $result->decimals);
    }

    /**
     * TIME_TO_SEC: the whole seconds of a time.
     */
    public function toSeconds(Frame $frame, Evaluable $argument): ?int
    {
        $time = (new Readings())->time($frame, $argument);
        if ($time === null) {
            return null;
        }
        $seconds = ($time[1] * 60 + $time[2]) * 60 + $time[3];

        return $time[0] ? -$seconds : $seconds;
    }
}
