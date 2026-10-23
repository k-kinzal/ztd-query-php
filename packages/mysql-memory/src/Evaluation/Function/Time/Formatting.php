<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

/**
 * The functions that write dates and times by a format: DATE_FORMAT and TIME_FORMAT, and GET_FORMAT, which answers formats.
 *
 * Each `%` specifier writes a part of the value; `%` before any other character writes that
 * character, and a format that writes nothing makes the result NULL. The names of days and
 * months are those of lc_time_names; a zero month has no name and a zero date no day of the
 * week, which makes the result NULL. TIME_FORMAT writes a time: its hours may pass 23, a negative
 * time writes a minus sign first, and the specifiers of the date make the result NULL except
 * those that write numbers, which write zeros. Weeks are counted as WEEK counts them, also for a
 * date with a zero month or day (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-format,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_get-format.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Formatting
{
    /**
     * The formats GET_FORMAT answers, by kind and standard.
     */
    public const FORMATS = [
        'DATE' => ['USA' => '%m.%d.%Y', 'JIS' => '%Y-%m-%d', 'ISO' => '%Y-%m-%d', 'EUR' => '%d.%m.%Y', 'INTERNAL' => '%Y%m%d'],
        'DATETIME' => ['USA' => '%Y-%m-%d %H.%i.%s', 'JIS' => '%Y-%m-%d %H:%i:%s', 'ISO' => '%Y-%m-%d %H:%i:%s', 'EUR' => '%Y-%m-%d %H.%i.%s', 'INTERNAL' => '%Y%m%d%H%i%s'],
        'TIME' => ['USA' => '%h:%i:%s %p', 'JIS' => '%H:%i:%s', 'ISO' => '%H:%i:%s', 'EUR' => '%H.%i.%s', 'INTERNAL' => '%H%i%s'],
    ];

    /**
     * The specifiers TIME_FORMAT does not write.
     */
    public const UNTIMED = 'abDjMUuVvWwXx';

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('DATE_FORMAT', 2, 2, fn (Frame $f, array $a): ?string => $this->date($f, $a[0], $a[1])),
            new Routine('TIME_FORMAT', 2, 2, fn (Frame $f, array $a): ?string => $this->time($f, $a[0], $a[1])),
        ];
    }

    /**
     * DATE_FORMAT: a date and time written by a format.
     */
    public function date(Frame $frame, Evaluable $value, Evaluable $format): ?string
    {
        $parts = (new Readings())->moment($frame, $value, Zeros::Dated);
        if ($parts === null) {
            return null;
        }
        $text = $format->evaluate($frame);

        return $text === null ? null : $this->write($parts, (string) $text, false, $frame);
    }

    /**
     * TIME_FORMAT: a time written by a format.
     */
    public function time(Frame $frame, Evaluable $value, Evaluable $format): ?string
    {
        $time = (new Readings())->time($frame, $value);
        if ($time === null) {
            return null;
        }
        $text = $format->evaluate($frame);
        if ($text === null) {
            return null;
        }
        $written = $this->write([0, 0, 0, $time[1], $time[2], $time[3], $time[4]], (string) $text, true, $frame);

        return $written === null || !$time[0] ? $written : '-' . $written;
    }

    /**
     * GET_FORMAT: the format of a kind of value in a standard, or NULL for an unknown standard.
     */
    public function standard(TemporalFormat $kind, int|float|string|null $standard): ?string
    {
        if ($standard === null) {
            return null;
        }
        $formats = self::FORMATS[$kind === TemporalFormat::Timestamp ? 'DATETIME' : $kind->value];

        return $formats[strtoupper((string) $standard)] ?? null;
    }

    /**
     * Writes the parts of a value by a format, or answers null when a specifier has nothing to write or the format writes nothing.
     *
     * @param array{int, int, int, int, int, int, int} $parts Year, month, day, hour, minute, second and microsecond
     * @param bool $time Whether the value is a time, whose hours may pass 23 and whose date is not written
     */
    public function write(array $parts, string $format, bool $time, Frame $frame): ?string
    {
        $locale = Locale::named((string) $frame->context->variables->read('lc_time_names')) ?? Locale::default();
        $output = '';
        for ($index = 0, $size = strlen($format); $index < $size; $index++) {
            if ($format[$index] !== '%' || $index + 1 >= $size) {
                $output .= $format[$index];
                continue;
            }
            $specifier = $format[++$index];
            if ($time && str_contains(self::UNTIMED, $specifier)) {
                return null;
            }
            $written = $this->specifier($specifier, $parts, $locale);
            if ($written === null) {
                return null;
            }
            $output .= $written;
        }

        return $output === '' ? null : $output;
    }

    /**
     * Writes one specifier of the parts of a value, or answers null when it has nothing to write.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function specifier(string $specifier, array $parts, Locale $locale): ?string
    {
        return match (true) {
            str_contains('abMWw', $specifier) => $this->name($specifier, $parts, $locale),
            str_contains('UuVvXx', $specifier) => $this->week($specifier, $parts),
            str_contains('fHhIiklprSsT', $specifier) => $this->clock($specifier, $parts),
            default => $this->calendar($specifier, $parts),
        };
    }

    /**
     * Writes a specifier of a name or of the day of the week, %a, %b, %M, %W or %w, or answers null when the value has none.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function name(string $specifier, array $parts, Locale $locale): ?string
    {
        [$year, $month, $day] = $parts;
        $undated = $year === 0 && $month === 0;
        $weekday = Readings::weekday(Readings::dayNumber($year, $month, $day));

        return match ($specifier) {
            'a' => $undated ? null : $locale->shortDays[$weekday] ?? null,
            'b' => $month === 0 ? null : $locale->shortMonths[$month - 1] ?? null,
            'M' => $month === 0 ? null : $locale->months[$month - 1] ?? null,
            'W' => $undated ? null : $locale->days[$weekday] ?? null,
            'w' => $undated ? null : (string) (($weekday + 1) % 7),
            default => $specifier,
        };
    }

    /**
     * Writes a specifier of the week, %U, %u, %V or %v, or of the year of the week, %X or %x, counted as WEEK counts them.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function week(string $specifier, array $parts): string
    {
        [$year, $month, $day] = $parts;
        $weeks = new Weeks();

        return match ($specifier) {
            'U' => sprintf('%02d', $weeks->week($year, $month, $day, 0)[0]),
            'u' => sprintf('%02d', $weeks->week($year, $month, $day, 1)[0]),
            'V' => sprintf('%02d', $weeks->week($year, $month, $day, 2)[0]),
            'v' => sprintf('%02d', $weeks->week($year, $month, $day, 3)[0]),
            'X' => $this->year($weeks->week($year, $month, $day, 2)[1]),
            'x' => $this->year($weeks->week($year, $month, $day, 3)[1]),
            default => $specifier,
        };
    }

    /**
     * Writes a specifier of the time of day: %f, %H, %h, %I, %i, %k, %l, %p, %r, %S, %s or %T.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function clock(string $specifier, array $parts): string
    {
        [, , , $hour, $minute, $second, $micro] = $parts;
        $twelve = $hour % 12 === 0 ? 12 : $hour % 12;
        $meridian = $hour % 24 >= 12 ? 'PM' : 'AM';

        return match ($specifier) {
            'f' => sprintf('%06d', $micro),
            'H' => sprintf('%02d', $hour),
            'h', 'I' => sprintf('%02d', $twelve),
            'i' => sprintf('%02d', $minute),
            'k' => (string) $hour,
            'l' => (string) $twelve,
            'p' => $meridian,
            'r' => sprintf('%02d:%02d:%02d %s', $hour % 24 % 12 === 0 ? 12 : $hour % 24 % 12, $minute, $second, $meridian),
            'S', 's' => sprintf('%02d', $second),
            'T' => sprintf('%02d:%02d:%02d', $hour, $minute, $second),
            default => $specifier,
        };
    }

    /**
     * Writes a specifier of the date in numbers, %c, %D, %d, %e, %j, %m, %Y or %y; any other character is written as itself.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function calendar(string $specifier, array $parts): string
    {
        [$year, $month, $day] = $parts;

        return match ($specifier) {
            'c' => (string) $month,
            'D' => $day . $this->suffix($day),
            'd' => sprintf('%02d', $day),
            'e' => (string) $day,
            'j' => sprintf('%03d', Readings::dayNumber($year, $month, $day) - Readings::dayNumber($year, 1, 1) + 1),
            'm' => sprintf('%02d', $month),
            'Y' => sprintf('%04d', $year),
            'y' => sprintf('%02d', $year % 100),
            default => $specifier,
        };
    }

    /**
     * Answers the English suffix of a day of the month: st, nd, rd or th.
     */
    public function suffix(int $day): string
    {
        $last = $day % 10;
        if (intdiv($day, 10) === 1 || $last < 1 || $last > 3) {
            return 'th';
        }

        return ['st', 'nd', 'rd'][$last - 1];
    }

    /**
     * Writes the year of a week with four digits; a year before 0 is written as the server's unsigned 32-bit number.
     */
    public function year(int $year): string
    {
        return sprintf('%04d', $year < 0 ? $year + 4294967296 : $year);
    }
}
