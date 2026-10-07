<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Moments;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use MySqlMemory\Value\Temporal;

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
        $part = static fn (int $length): \Closure => static fn (array $d, Signature $s): Domain => Domain::integer(Field::LongLong, $length)->withNullable(true);
        $routines = [
            new Routine('DATE', 1, 1, static fn (array $d, Signature $s): Domain => new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::binary(), true), fn (Frame $f, array $a): ?string => $this->moment($f, $a[0], Kind::Date)),
        ];
        $parts = [
            'YEAR' => [4, static fn (array $p): int => $p[0], Kind::Date],
            'MONTH' => [2, static fn (array $p): int => $p[1], Kind::Date],
            'DAY' => [2, static fn (array $p): int => $p[2], Kind::Date],
            'DAYOFMONTH' => [2, static fn (array $p): int => $p[2], Kind::Date],
            'HOUR' => [3, static fn (array $p): int => $p[3], Kind::Time],
            'MINUTE' => [2, static fn (array $p): int => $p[4], Kind::Time],
            'SECOND' => [2, static fn (array $p): int => $p[5], Kind::Time],
            'MICROSECOND' => [6, static fn (array $p): int => $p[6], Kind::Time],
            'QUARTER' => [1, static fn (array $p): int => intdiv($p[1] + 2, 3), Kind::Date],
            'DAYOFYEAR' => [3, static fn (array $p): int => (int) date('z', (int) gmmktime(0, 0, 0, $p[1], $p[2], $p[0])) + 1, Kind::Date],
            'DAYOFWEEK' => [1, static fn (array $p): int => (int) gmdate('w', (int) gmmktime(0, 0, 0, $p[1], $p[2], $p[0])) + 1, Kind::Date],
            'WEEKDAY' => [1, static fn (array $p): int => ((int) gmdate('w', (int) gmmktime(0, 0, 0, $p[1], $p[2], $p[0])) + 6) % 7, Kind::Date],
        ];
        foreach ($parts as $name => [$length, $read, $kind]) {
            $routines[] = new Routine($name, 1, 1, $part($length), fn (Frame $f, array $a): ?int => $this->part($f, $a[0], $read, $kind));
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
     *
     * @param callable(list<int>): int $read
     */
    public function part(Frame $frame, Evaluable $argument, callable $read, Kind $kind): ?int
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $argument->domain();
        if ($kind === Kind::Time && ($domain->kind === Kind::Time || $domain->kind === Kind::String && Temporal::parseDateTime((string) $value) === null)) {
            $time = (new Moments())->time($value, $domain, 6, $frame->context);
            if ($time === null) {
                return null;
            }
            $parts = Temporal::parseTime($time);

            return $parts === null ? null : $read([0, 0, 0, $parts[1], $parts[2], $parts[3], $parts[4]]);
        }
        $moment = (new Moments())->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $frame->context);
        $parts = $moment === null ? null : Temporal::parseDateTime($moment);

        return $parts === null ? null : $read($parts);
    }
}
