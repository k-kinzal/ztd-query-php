<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Interval;
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
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
     */
    #[Override]
    public function evaluate(Frame $frame): ?string
    {
        $value = $this->operand->evaluate($frame);
        $quantity = $this->quantity->evaluate($frame);
        if ($value === null || $quantity === null) {
            return null;
        }
        $interval = Interval::read((string) Convert::toText($quantity, $this->quantity->domain()), $this->unit);
        if ($interval === null) {
            return null;
        }
        $months = $this->subtract ? -$interval->months : $interval->months;
        $micro = $this->subtract ? -$interval->microseconds : $interval->microseconds;
        if ($this->domain->kind === Kind::Time) {
            return $this->time((string) $value, $micro);
        }
        $domain = $this->operand->domain();
        $text = $domain->kind === Kind::String || $domain->kind->temporal() ? (string) Convert::toText($value, $domain) : (string) Convert::toDecimal($value, $domain, $frame->context);
        $parts = Temporal::parseDateTime($text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[1] === 0 || $parts[2] === 0) {
            $frame->context->warning(ErrorCode::TruncatedWrongValue, 'datetime', $text);

            return null;
        }
        [$year, $month, $day, $hour, $minute, $second, $fraction, $timed] = $parts;
        if ($months !== 0) {
            $moved = Calendar::addMonths($year, $month, $day, $months);
            if ($moved === null) {
                $frame->context->warning(ErrorCode::DatetimeFunctionOverflow, 'datetime');

                return null;
            }
            [$year, $month, $day] = $moved;
        }
        $moved = Calendar::addMicroseconds($year, $month, $day, $hour, $minute, $second, $fraction, $micro);
        if ($moved === null) {
            $frame->context->warning(ErrorCode::DatetimeFunctionOverflow, 'datetime');

            return null;
        }

        return $this->write($moved, $timed || $fraction !== 0 || !Interval::dated($this->unit), $fraction !== 0 || $moved[6] !== 0);
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
