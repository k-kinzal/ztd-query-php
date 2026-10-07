<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
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
}
