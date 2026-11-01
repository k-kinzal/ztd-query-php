<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

/**
 * The functions that count days, weeks and months: ADDDATE and SUBDATE with a number of days, TO_DAYS, FROM_DAYS, TO_SECONDS, DATEDIFF, MAKEDATE, LAST_DAY, DAYNAME, MONTHNAME, WEEK, WEEKOFYEAR, YEARWEEK, PERIOD_ADD and PERIOD_DIFF.
 *
 * Days are counted from 0000-01-01, day 1. FROM_DAYS gives the zero date up to day 365 and from
 * day 3652500, and NULL with ER_DATETIME_FUNCTION_OVERFLOW past 9999-12-31, which MySQL 5.6 and
 * 5.7 write as a year of five digits; MAKEDATE reads a
 * year below 100 as 2000-2069 or 1970-1999 and is NULL for a day below 1 or past 9999. DAYNAME
 * and MONTHNAME write the names of lc_time_names. WEEK takes its mode from default_week_format
 * when none is given, NULL being mode 0; YEARWEEK numbers the weeks from 1. A period is YYMM or
 * YYYYMM, a two-digit year being 2000-2069 or 1970-1999; a period whose month is not 1 to 12 is
 * ER_WRONG_ARGUMENTS; the months are counted in unsigned 64-bit arithmetic (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Calendars
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('TO_DAYS', 1, 1, fn (Frame $f, array $a): ?int => $this->days($f, $a[0])),
            new Routine('TO_SECONDS', 1, 1, fn (Frame $f, array $a): ?int => $this->seconds($f, $a[0])),
            new Routine('FROM_DAYS', 1, 1, fn (Frame $f, array $a): ?string => $this->fromDays($f, $a[0])),
            new Routine('DATEDIFF', 2, 2, fn (Frame $f, array $a): ?int => $this->difference($f, $a[0], $a[1])),
            new Routine('MAKEDATE', 2, 2, fn (Frame $f, array $a): ?string => $this->make($f, $a[0], $a[1])),
            new Routine('LAST_DAY', 1, 1, fn (Frame $f, array $a): ?string => $this->lastDay($f, $a[0])),
            new Routine('DAYNAME', 1, 1, fn (Frame $f, array $a): ?string => $this->dayName($f, $a[0])),
            new Routine('MONTHNAME', 1, 1, fn (Frame $f, array $a): ?string => $this->monthName($f, $a[0])),
            new Routine('WEEK', 1, 2, fn (Frame $f, array $a): ?int => $this->week($f, $a, false)),
            new Routine('WEEKOFYEAR', 1, 1, fn (Frame $f, array $a): ?int => $this->weekOfYear($f, $a[0])),
            new Routine('YEARWEEK', 1, 2, fn (Frame $f, array $a): ?int => $this->week($f, $a, true)),
            new Routine('ADDDATE', 2, 2, static fn (Frame $f, array $a, Domain $r): ?string => (new DateShift($a[0], $a[1], IntervalUnit::Day, false, $r))->evaluate($f)),
            new Routine('SUBDATE', 2, 2, static fn (Frame $f, array $a, Domain $r): ?string => (new DateShift($a[0], $a[1], IntervalUnit::Day, true, $r))->evaluate($f)),
            new Routine('PERIOD_ADD', 2, 2, fn (Frame $f, array $a): ?int => $this->periodAdd($f, $a[0], $a[1])),
            new Routine('PERIOD_DIFF', 2, 2, fn (Frame $f, array $a): ?int => $this->periodDifference($f, $a[0], $a[1])),
        ];
    }

    /**
     * TO_DAYS: the day number of a date.
     */
    public function days(Frame $frame, Evaluable $argument): ?int
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Refused);

        return $parts === null ? null : Readings::dayNumber($parts[0], $parts[1], $parts[2]);
    }

    /**
     * TO_SECONDS: the seconds since 0000-01-01 00:00:00, day 1 being its first day.
     */
    public function seconds(Frame $frame, Evaluable $argument): ?int
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Refused);

        return $parts === null ? null : Readings::dayNumber($parts[0], $parts[1], $parts[2]) * 86400 + $parts[3] * 3600 + $parts[4] * 60 + $parts[5];
    }

    /**
     * FROM_DAYS: the date of a day number.
     */
    public function fromDays(Frame $frame, Evaluable $argument): ?string
    {
        $days = (new Readings())->integer($frame, $argument);
        if ($days === null) {
            return null;
        }
        if ($days <= 365 || $days >= 3652500) {
            return '0000-00-00';
        }
        if ($days > 3652424 && !$this->legacy($frame)) {
            $frame->context->warning(DataError::DatetimeFunctionOverflow, 'from_days');

            return null;
        }

        return Temporal::date(...Calendar::date($days));
    }

    /**
     * DATEDIFF: the days from the second date to the first.
     */
    public function difference(Frame $frame, Evaluable $left, Evaluable $right): ?int
    {
        $readings = new Readings();
        $first = $readings->moment($frame, $left, Zeros::Refused);
        if ($first === null) {
            return null;
        }
        $second = $readings->moment($frame, $right, Zeros::Refused);

        return $second === null ? null : Readings::dayNumber($first[0], $first[1], $first[2]) - Readings::dayNumber($second[0], $second[1], $second[2]);
    }

    /**
     * MAKEDATE: the date of a day of a year.
     */
    public function make(Frame $frame, Evaluable $year, Evaluable $day): ?string
    {
        $readings = new Readings();
        $number = $readings->integer($frame, $year);
        $ordinal = $readings->integer($frame, $day);
        if ($number === null || $ordinal === null || $number < 0 || $number > 9999 || $ordinal <= 0) {
            return null;
        }
        if ($number < 100) {
            $number += $number < 70 ? 2000 : 1900;
        }
        $days = Readings::dayNumber($number, 1, 1) + $ordinal - 1;
        if ($ordinal > 3652424 || $days > 3652424) {
            return null;
        }

        return Temporal::date(...Calendar::date($days));
    }

    /**
     * LAST_DAY: the last day of the month of a date.
     */
    public function lastDay(Frame $frame, Evaluable $argument): ?string
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Months);

        return $parts === null ? null : Temporal::date($parts[0], $parts[1], Calendar::monthLength($parts[0], $parts[1]));
    }

    /**
     * DAYNAME: the name of the day of the week of a date.
     */
    public function dayName(Frame $frame, Evaluable $argument): ?string
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Refused);

        return $parts === null ? null : $this->locale($frame)->days[Readings::weekday(Readings::dayNumber($parts[0], $parts[1], $parts[2]))] ?? null;
    }

    /**
     * MONTHNAME: the name of the month of a date; NULL for a zero month.
     */
    public function monthName(Frame $frame, Evaluable $argument): ?string
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Dated);

        return $parts === null || $parts[1] === 0 ? null : $this->locale($frame)->months[$parts[1] - 1] ?? null;
    }

    /**
     * Answers the locale of the session (lc_time_names).
     */
    public function locale(Frame $frame): Locale
    {
        return Locale::named((string) $frame->context->variables->read('lc_time_names')) ?? Locale::default();
    }

    /**
     * WEEK and YEARWEEK: the week of a date in a mode; YEARWEEK numbers the weeks from 1 and writes the year first.
     *
     * @param list<Evaluable> $arguments
     */
    public function week(Frame $frame, array $arguments, bool $yearly): ?int
    {
        $parts = (new Readings())->moment($frame, $arguments[0], Zeros::Refused);
        if ($parts === null) {
            return null;
        }
        $mode = isset($arguments[1]) ? (new Readings())->integer($frame, $arguments[1]) ?? 0 : ($yearly ? 0 : (int) $frame->context->variables->read('default_week_format'));
        [$week, $year] = (new Weeks())->week($parts[0], $parts[1], $parts[2], ($mode & 7) | ($yearly ? 2 : 0));
        if (!$yearly) {
            return $week;
        }
        $value = $year * 100 + $week;

        return $value < 0 ? $value + 4294967296 : $value;
    }

    /**
     * WEEKOFYEAR: the week of a date in mode 3, as ISO 8601 numbers it.
     */
    public function weekOfYear(Frame $frame, Evaluable $argument): ?int
    {
        $parts = (new Readings())->moment($frame, $argument, Zeros::Refused);

        return $parts === null ? null : (new Weeks())->week($parts[0], $parts[1], $parts[2], 3)[0];
    }

    /**
     * PERIOD_ADD: a period moved by a number of months.
     *
     * @throws SqlError When the period has no month from 1 to 12
     */
    public function periodAdd(Frame $frame, Evaluable $period, Evaluable $months): ?int
    {
        $readings = new Readings();
        $value = $readings->integer($frame, $period);
        $count = $readings->integer($frame, $months);
        if ($value === null || $count === null) {
            return null;
        }
        if ($this->legacy($frame)) {
            if ($value === 0) {
                return 0;
            }
            $months = (int) bcmod(bcadd($this->legacyMonths($value), bcmod((string) $count, '4294967296')), '4294967296');
            $months = $months < 0 ? $months + 4294967296 : $months;

            return $months === 0 ? 0 : intdiv($months, 12) * 100 + $months % 12 + 1;
        }
        $total = bcmod(bcadd($this->months($value, 'period_add'), (string) $count), '18446744073709551616');
        $total = bccomp($total, '0') < 0 ? bcadd($total, '18446744073709551616') : $total;
        if ($total === '0') {
            return 0;
        }

        return $this->signed(bcmod(bcadd(bcmul(bcdiv($total, '12', 0), '100'), bcadd(bcmod($total, '12'), '1')), '18446744073709551616'));
    }

    /**
     * PERIOD_DIFF: the months from the second period to the first.
     *
     * @throws SqlError When a period has no month from 1 to 12
     */
    public function periodDifference(Frame $frame, Evaluable $left, Evaluable $right): ?int
    {
        $readings = new Readings();
        $first = $readings->integer($frame, $left);
        $second = $readings->integer($frame, $right);
        if ($first === null || $second === null) {
            return null;
        }
        if ($this->legacy($frame)) {
            return (int) $this->legacyMonths($first) - (int) $this->legacyMonths($second);
        }
        $difference = bcmod(bcsub($this->months($first, 'period_diff'), $this->months($second, 'period_diff')), '18446744073709551616');

        return $this->signed(bccomp($difference, '0') < 0 ? bcadd($difference, '18446744073709551616') : $difference);
    }

    /**
     * Answers the months since year 0 of a period.
     *
     * @return numeric-string
     *
     * @throws SqlError When the period has no month from 1 to 12
     */
    public function months(int $period, string $function): string
    {
        $month = $period % 100;
        if ($period <= 0 || $month === 0 || $month > 12) {
            throw StatementError::WrongArguments->error($function);
        }
        $year = intdiv($period, 100);
        if ($year < 100) {
            $year += $year < 70 ? 2000 : 1900;
        }

        return bcadd(bcmul((string) $year, '12'), (string) ($month - 1));
    }

    /**
     * Tells whether MySQL 5.6 or 5.7 is emulated, whose periods are counted in unsigned 32-bit arithmetic without a check of the month (verified on a live 5.7.44 server).
     */
    public function legacy(Frame $frame): bool
    {
        $release = $frame->context->modes->release;

        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
    }

    /**
     * Answers the months since year 0 of a period as MySQL 5.6 and 5.7 count them: the period read as unsigned, the months kept to 32 bits; zero for the period 0.
     *
     * @return numeric-string
     */
    public function legacyMonths(int $period): string
    {
        if ($period === 0) {
            return '0';
        }
        $unsigned = $period < 0 ? bcadd((string) $period, '18446744073709551616') : (string) $period;
        $year = bcdiv($unsigned, '100', 0);
        if (bccomp($year, '100') < 0) {
            $year = bcadd($year, (int) $year < 70 ? '2000' : '1900');
        }

        return bcmod(bcadd(bcmul($year, '12'), (string) ((int) bcmod($unsigned, '100') - 1)), '4294967296');
    }

    /**
     * Reads an unsigned 64-bit number as the signed BIGINT of the same bits.
     *
     * @param numeric-string $unsigned
     */
    public function signed(string $unsigned): int
    {
        return bccomp($unsigned, '9223372036854775807') > 0 ? (int) bcsub($unsigned, '18446744073709551616') : (int) $unsigned;
    }
}
