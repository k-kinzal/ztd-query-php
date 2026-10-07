<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Interval;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles date arithmetic: the result is a DATE, DATETIME or TIME for an operand of that kind, else a string.
 *
 * A DATE moved by a unit smaller than a day becomes a DATETIME. Any other operand gives a
 * string of 29 characters, a date or a datetime as the value reached.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Dates
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles `expr + INTERVAL n unit` and `expr - INTERVAL n unit`.
     */
    public function arithmetic(IntervalArithmetic $node, Scope $scope): Evaluable
    {
        return $this->shift($node->operand, $node->interval->quantity, $node->interval->unit, $node->subtract, $scope);
    }

    /**
     * Compiles `INTERVAL n unit + expr`.
     */
    public function addition(IntervalAddition $node, Scope $scope): Evaluable
    {
        return $this->shift($node->operand, $node->interval->quantity, $node->interval->unit, false, $scope);
    }

    /**
     * Compiles DATE_ADD and DATE_SUB.
     */
    public function call(DateArithmetic $node, Scope $scope): Evaluable
    {
        return $this->shift($node->date, $node->quantity, $node->unit, $node->subtract, $scope);
    }

    /**
     * Compiles a date moved by an interval.
     */
    public function shift(Scalar $operand, Scalar $quantity, IntervalUnit $unit, bool $subtract, Scope $scope): Evaluable
    {
        $date = $this->compiler->compile($operand, $scope);
        $amount = $this->compiler->compile($quantity, $scope);

        return new DateShift($date, $amount, $unit, $subtract, $this->domain($date->domain(), $unit));
    }

    /**
     * Answers the domain of a date moved by a unit.
     */
    public function domain(Domain $operand, IntervalUnit $unit): Domain
    {
        $micro = in_array($unit, [IntervalUnit::Microsecond, IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::HourMicrosecond, IntervalUnit::DayMicrosecond], true) ? 6 : 0;

        return match ($operand->kind) {
            Kind::Date => Interval::dated($unit) ? new Domain(Kind::Date, Field::Date, 10) : new Domain(Kind::DateTime, Field::DateTime, 19 + ($micro > 0 ? 7 : 0), $micro),
            Kind::DateTime => new Domain(Kind::DateTime, Field::DateTime, max($operand->length, 19 + ($micro > 0 ? 7 : 0)), max($operand->decimals, $micro)),
            Kind::Time => new Domain(Kind::Time, Field::Time, max($operand->length, 10 + ($micro > 0 ? 7 : 0)), max($operand->decimals, $micro)),
            default => Domain::string(29, $this->compiler->settings->connectionCollation, Field::String)->withNullable(true),
        };
    }

    /**
     * Compiles EXTRACT(unit FROM value): the parts of the unit, written together as one integer.
     */
    public function extract(\SqlSemantics\Platform\MySql\Statement\Call\Extract $node, Scope $scope): Evaluable
    {
        $source = $this->compiler->compile($node->source, $scope);
        $unit = $node->unit;
        $lengths = ['YEAR' => 4, 'MONTH' => 2, 'DAY' => 2, 'HOUR' => 2, 'MINUTE' => 2, 'SECOND' => 2, 'MICROSECOND' => 6, 'WEEK' => 2, 'QUARTER' => 1, 'YEAR_MONTH' => 6, 'DAY_HOUR' => 4, 'DAY_MINUTE' => 6, 'DAY_SECOND' => 8, 'HOUR_MINUTE' => 4, 'HOUR_SECOND' => 6, 'MINUTE_SECOND' => 4, 'DAY_MICROSECOND' => 14, 'HOUR_MICROSECOND' => 12, 'MINUTE_MICROSECOND' => 10, 'SECOND_MICROSECOND' => 8];
        $domain = Domain::integer(Field::LongLong, ($lengths[$unit->value] ?? 2) + 1)->withNullable(true);
        $moments = new \MySqlMemory\Evaluation\Operator\Moments();

        return (new Texts($this->compiler))->call('EXTRACT', [$source], $domain, static function (\MySqlMemory\Evaluation\Frame $f, array $a) use ($unit, $moments): ?int {
            $value = $a[0]->evaluate($f);
            if ($value === null) {
                return null;
            }
            $domain = $a[0]->domain();
            $timeOnly = in_array($unit, [IntervalUnit::Hour, IntervalUnit::Minute, IntervalUnit::Second, IntervalUnit::Microsecond, IntervalUnit::HourMinute, IntervalUnit::HourSecond, IntervalUnit::MinuteSecond, IntervalUnit::HourMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::SecondMicrosecond], true);
            if ($domain->kind === Kind::Time || ($timeOnly && $domain->kind === Kind::String && \MySqlMemory\Value\Temporal::parseDateTime((string) $value) === null)) {
                $time = $moments->time($value, $domain, 6, $f->context);
                $t = $time === null ? null : \MySqlMemory\Value\Temporal::parseTime($time);
                $parts = $t === null ? null : [0, 0, 0, $t[1], $t[2], $t[3], $t[4]];
                $sign = $t !== null && $t[0] ? -1 : 1;
            } else {
                $moment = $moments->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $f->context);
                $parts = $moment === null ? null : \MySqlMemory\Value\Temporal::parseDateTime($moment);
                $sign = 1;
            }
            if ($parts === null) {
                return null;
            }
            [$year, $month, $day, $hour, $minute, $second, $micro] = $parts;

            return $sign * match ($unit) {
                IntervalUnit::Year => $year,
                IntervalUnit::Month => $month,
                IntervalUnit::Day => $day,
                IntervalUnit::Hour => $hour,
                IntervalUnit::Minute => $minute,
                IntervalUnit::Second => $second,
                IntervalUnit::Microsecond => $micro,
                IntervalUnit::Quarter => intdiv($month + 2, 3),
                IntervalUnit::Week => (int) gmdate('W', (int) gmmktime(0, 0, 0, max(1, $month), max(1, $day), max(1970, $year))),
                IntervalUnit::YearMonth => $year * 100 + $month,
                IntervalUnit::DayHour => $day * 100 + $hour,
                IntervalUnit::DayMinute => ($day * 100 + $hour) * 100 + $minute,
                IntervalUnit::DaySecond => (($day * 100 + $hour) * 100 + $minute) * 100 + $second,
                IntervalUnit::HourMinute => $hour * 100 + $minute,
                IntervalUnit::HourSecond => ($hour * 100 + $minute) * 100 + $second,
                IntervalUnit::MinuteSecond => $minute * 100 + $second,
                IntervalUnit::DayMicrosecond => ((($day * 100 + $hour) * 100 + $minute) * 100 + $second) * 1000000 + $micro,
                IntervalUnit::HourMicrosecond => (($hour * 100 + $minute) * 100 + $second) * 1000000 + $micro,
                IntervalUnit::MinuteMicrosecond => ($minute * 100 + $second) * 1000000 + $micro,
                IntervalUnit::SecondMicrosecond => $second * 1000000 + $micro,
            };
        });
    }
}
