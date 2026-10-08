<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The date and time functions that read parts of a value: DATE, YEAR, MONTH, DAY, HOUR, MINUTE, SECOND and the others.
 *
 * An argument that is no valid date or time makes the result NULL with a warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Dates
{
    /**
     * The parts each unit of EXTRACT other than QUARTER and WEEK reads, by the unit keyword.
     *
     * Each part is its position among the year, month, day, hour, minute, second and microsecond
     * of a moment; the parts are written together, two digits for each part after the first, six
     * for microseconds.
     */
    public const UNIT_PARTS = [
        'YEAR' => [0],
        'MONTH' => [1],
        'DAY' => [2],
        'HOUR' => [3],
        'MINUTE' => [4],
        'SECOND' => [5],
        'MICROSECOND' => [6],
        'YEAR_MONTH' => [0, 1],
        'DAY_HOUR' => [2, 3],
        'DAY_MINUTE' => [2, 3, 4],
        'DAY_SECOND' => [2, 3, 4, 5],
        'DAY_MICROSECOND' => [2, 3, 4, 5, 6],
        'HOUR_MINUTE' => [3, 4],
        'HOUR_SECOND' => [3, 4, 5],
        'HOUR_MICROSECOND' => [3, 4, 5, 6],
        'MINUTE_SECOND' => [4, 5],
        'MINUTE_MICROSECOND' => [4, 5, 6],
        'SECOND_MICROSECOND' => [5, 6],
    ];

    /**
     * The units of EXTRACT that read only the time of a value.
     */
    public const TIME_UNITS = [IntervalUnit::Hour, IntervalUnit::Minute, IntervalUnit::Second, IntervalUnit::Microsecond, IntervalUnit::HourMinute, IntervalUnit::HourSecond, IntervalUnit::MinuteSecond, IntervalUnit::HourMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::SecondMicrosecond];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $routines = [
            new Routine('DATE', 1, 1, fn (Frame $f, array $a): ?string => $this->moment($f, $a[0], Kind::Date)),
        ];
        foreach (['YEAR', 'MONTH', 'DAY', 'DAYOFMONTH', 'QUARTER', 'DAYOFYEAR', 'DAYOFWEEK', 'WEEKDAY'] as $name) {
            $routines[] = new Routine($name, 1, 1, fn (Frame $f, array $a): ?int => $this->part($f, $a[0], $name, Kind::Date));
        }
        foreach (['HOUR', 'MINUTE', 'SECOND', 'MICROSECOND'] as $name) {
            $routines[] = new Routine($name, 1, 1, fn (Frame $f, array $a): ?int => $this->part($f, $a[0], $name, Kind::Time));
        }

        return $routines;
    }

    /**
     * Converts an argument to a date or a time.
     */
    public function moment(Frame $frame, Evaluable $argument, Kind $kind): ?string
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }

        return (new Moments())->convert($value, $argument->domain(), new Domain($kind, $kind === Kind::Date ? Field::Date : Field::DateTime, 26, 6), $frame->context);
    }

    /**
     * Reads a part of the date or time of an argument.
     */
    public function part(Frame $frame, Evaluable $argument, string $name, Kind $kind): ?int
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $argument->domain();
        if ($kind === Kind::Time && ($domain->kind === Kind::Time || ($domain->kind === Kind::String && Temporal::parseTime((string) $value) !== null))) {
            $time = (new Moments())->time($value, $domain, 6, $frame->context);
            if ($time === null) {
                return null;
            }
            $parts = Temporal::parseTime($time);

            return $parts === null ? null : $this->read($name, [0, 0, 0, $parts[1], $parts[2], $parts[3], $parts[4]]);
        }
        $moment = (new Moments())->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $frame->context);
        $parts = $moment === null ? null : Temporal::parseDateTime($moment);

        return $parts === null ? null : $this->read($name, $parts);
    }

    /**
     * Reads a named part from the year, month, day, hour, minute, second and microsecond of a moment.
     *
     * @param array{int, int, int, int, int, int, int, 7?: bool} $parts
     */
    public function read(string $name, array $parts): int
    {
        $day = static fn (): int => (int) gmmktime(0, 0, 0, $parts[1], $parts[2], $parts[0]);

        return match ($name) {
            'YEAR' => $parts[0],
            'MONTH' => $parts[1],
            'QUARTER' => intdiv($parts[1] + 2, 3),
            'DAYOFYEAR' => (int) gmdate('z', $day()) + 1,
            'DAYOFWEEK' => (int) gmdate('w', $day()) + 1,
            'WEEKDAY' => ((int) gmdate('w', $day()) + 6) % 7,
            'HOUR' => $parts[3],
            'MINUTE' => $parts[4],
            'SECOND' => $parts[5],
            'MICROSECOND' => $parts[6],
            default => $parts[2],
        };
    }

    /**
     * Reads the parts of a unit of the date or time of an argument, written together as one integer: EXTRACT(unit FROM value).
     *
     * A TIME, and a string that holds no datetime read for a unit of the time alone, is read as a
     * time, its parts negative when the time is.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_extract.
     */
    public function extract(Frame $frame, Evaluable $argument, IntervalUnit $unit): ?int
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $argument->domain();
        if ($domain->kind === Kind::Time || (in_array($unit, self::TIME_UNITS, true) && $domain->kind === Kind::String && Temporal::parseDateTime((string) $value) === null)) {
            $time = (new Moments())->time($value, $domain, 6, $frame->context);
            $parts = $time === null ? null : Temporal::parseTime($time);

            return $parts === null ? null : ($parts[0] ? -1 : 1) * $this->unit($unit, [0, 0, 0, $parts[1], $parts[2], $parts[3], $parts[4]]);
        }
        $moment = (new Moments())->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $frame->context);
        $parts = $moment === null ? null : Temporal::parseDateTime($moment);

        return $parts === null ? null : $this->unit($unit, $parts);
    }

    /**
     * Writes the parts of a unit from the year, month, day, hour, minute, second and microsecond of a moment together as one integer.
     *
     * @param array{int, int, int, int, int, int, int, 7?: bool} $parts
     */
    public function unit(IntervalUnit $unit, array $parts): int
    {
        if ($unit === IntervalUnit::Quarter) {
            return intdiv($parts[1] + 2, 3);
        }
        if ($unit === IntervalUnit::Week) {
            return (int) gmdate('W', (int) gmmktime(0, 0, 0, max(1, $parts[1]), max(1, $parts[2]), max(1970, $parts[0])));
        }
        $value = 0;
        foreach (self::UNIT_PARTS[$unit->value] as $index) {
            $value = $value * ($index === 6 ? 1000000 : 100) + $parts[$index];
        }

        return $value;
    }
}
