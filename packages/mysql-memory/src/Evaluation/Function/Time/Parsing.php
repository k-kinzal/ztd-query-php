<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

/**
 * STR_TO_DATE: a date, time or datetime read from a string by a format.
 *
 * The format is matched from the start of the string. Whitespace before each part of the string
 * is skipped; other characters of the format must be in the string as written. A number
 * specifier reads up to as many digits as it writes, a one- or two-digit %Y and %y reading as
 * 2000-2069 or 1970-1999; a name specifier reads a whole word and takes the English names in
 * any case; %p needs a twelve-hour specifier, and %% matches nothing. A string that ends before
 * the format is read as far as it goes; text after the format is a warning. A week specifier
 * with a day of the week gives the day of that week, and %j the day of the year. A value that
 * does not match, or a date that does not exist or has zero parts sql_mode refuses, is NULL with
 * ER_WRONG_VALUE_FOR_TYPE; here NO_ZERO_DATE refuses a zero year, month or day (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_str-to-date.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Parsing
{
    /**
     * The most digits each number specifier reads.
     */
    public const DIGITS = ['d' => 2, 'e' => 2, 'D' => 2, 'm' => 2, 'c' => 2, 'Y' => 4, 'y' => 2, 'H' => 2, 'k' => 2, 'h' => 2, 'I' => 2, 'l' => 2, 'i' => 2, 's' => 2, 'S' => 2, 'f' => 6, 'j' => 3, 'w' => 1, 'U' => 2, 'u' => 2, 'V' => 2, 'v' => 2, 'X' => 4, 'x' => 4];

    /**
     * The least and the greatest number each number specifier with a range reads.
     */
    public const RANGES = ['H' => [0, 23], 'k' => [0, 23], 'h' => [1, 12], 'I' => [1, 12], 'l' => [1, 12], 'i' => [0, 59], 's' => [0, 59], 'S' => [0, 59], 'j' => [1, PHP_INT_MAX], 'w' => [0, 6], 'U' => [0, 53], 'u' => [0, 53], 'V' => [0, 53], 'v' => [0, 53]];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('STR_TO_DATE', 2, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->read($f, $a[0], $a[1], $r)),
        ];
    }

    /**
     * STR_TO_DATE: reads a string by a format.
     */
    public function read(Frame $frame, Evaluable $value, Evaluable $format, Domain $result): ?string
    {
        $text = $value->evaluate($frame);
        if ($text === null) {
            return null;
        }
        $pattern = $format->evaluate($frame);
        if ($pattern === null) {
            return null;
        }
        $text = (string) $text;
        $fields = $this->scan($text, (string) $pattern);
        $context = $frame->context;
        if (!isset($fields['failed'])) {
            $parts = $this->resolve($fields);
            if ($parts !== null && $this->accepted($parts, $result, $context)) {
                if (trim((string) ($fields['rest'] ?? '')) !== '') {
                    $context->warning(DataError::TruncatedWrongValue, match ($result->kind) {
                        Kind::Date => 'date',
                        Kind::Time => 'time',
                        Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => 'datetime',
                    }, $text);
                }

                return $this->written($parts, $result);
            }
        }
        $context->warning(DataError::WrongValueForType, isset($fields['timed']) ? 'time' : 'datetime', $text, 'str_to_date');

        return null;
    }

    /**
     * Matches a string against a format and answers the fields read, with `failed` set when it does not match and `rest` holding the text after the format.
     *
     * @return array<string, int|string>
     */
    public function scan(string $text, string $format): array
    {
        $fields = [];
        $position = 0;
        $length = strlen($text);
        for ($index = 0, $size = strlen($format); $index < $size; $index++) {
            $character = $format[$index];
            if (ctype_space($character)) {
                continue;
            }
            while ($position < $length && ctype_space($text[$position])) {
                $position++;
            }
            if ($position >= $length) {
                return $fields;
            }
            if ($character !== '%' || $index + 1 >= $size) {
                if ($text[$position] !== $character) {
                    return ['failed' => 1];
                }
                $position++;
                continue;
            }
            $specifier = $format[++$index];
            if ($specifier === 'r' || $specifier === 'T') {
                $inner = $this->scan(substr($text, $position), $specifier === 'r' ? '%I:%i:%S %p' : '%H:%i:%S');
                if (isset($inner['failed'])) {
                    return ['failed' => 1, 'timed' => 1];
                }
                $position = $length - strlen((string) ($inner['rest'] ?? ''));
                unset($inner['rest']);
                $fields = [...$fields, ...$inner];
                continue;
            }
            $read = $this->field($specifier, $text, $position, $fields);
            if ($read === null) {
                return ['failed' => 1];
            }
            [$fields, $position] = $read;
        }
        $fields['rest'] = substr($text, $position);

        return $fields;
    }

    /**
     * Reads one specifier at a position of the string, answering the fields and the position after it, or null when the string does not match.
     *
     * @param array<string, int|string> $fields
     * @return array{array<string, int|string>, int}|null
     */
    public function field(string $specifier, string $text, int $position, array $fields): ?array
    {
        return isset(self::DIGITS[$specifier]) ? $this->digits($specifier, $text, $position, $fields) : $this->word($specifier, $text, $position, $fields);
    }

    /**
     * Reads the digits of a number specifier at a position of the string, and the suffix of %D after them, or answers null when there are none or the number is out of range.
     *
     * @param array<string, int|string> $fields
     * @return array{array<string, int|string>, int}|null
     */
    public function digits(string $specifier, string $text, int $position, array $fields): ?array
    {
        $start = $position + ($text[$position] === '+' ? 1 : 0);
        $end = $start;
        while ($end < strlen($text) && $end - $start < self::DIGITS[$specifier] && ctype_digit($text[$end])) {
            $end++;
        }
        if ($end === $start) {
            return null;
        }
        $fields = $this->number($specifier, substr($text, $start, $end - $start), $fields);
        if ($fields === null) {
            return null;
        }
        if ($specifier === 'D') {
            $end += min(2, strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $end));
        }

        return [$fields, $end];
    }

    /**
     * Reads a name specifier, a whole English word in any case, or any other specifier as the character it names, at a position of the string; null when the string does not match.
     *
     * @param array<string, int|string> $fields
     * @return array{array<string, int|string>, int}|null
     */
    public function word(string $specifier, string $text, int $position, array $fields): ?array
    {
        $word = strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $position);
        $name = strtolower(substr($text, $position, $word));
        $locale = Locale::default();
        $found = match ($specifier) {
            'M' => array_search($name, array_map('strtolower', $locale->months), true),
            'b' => array_search($name, array_map('strtolower', $locale->shortMonths), true),
            'W' => array_search($name, array_map('strtolower', $locale->days), true),
            'a' => array_search($name, array_map('strtolower', $locale->shortDays), true),
            'p' => ($fields['twelve'] ?? 0) === 1 && ($name === 'am' || $name === 'pm') ? $name : false,
            default => false,
        };
        if ($found !== false) {
            if ($specifier === 'M' || $specifier === 'b') {
                $fields['month'] = (int) $found + 1;
            } elseif ($specifier === 'W' || $specifier === 'a') {
                $fields['weekday'] = ((int) $found + 1) % 7;
            } else {
                $fields['meridian'] = (string) $found;
            }

            return [$fields, $position + $word];
        }
        if (in_array($specifier, ['M', 'b', 'W', 'a', 'p', '%'], true)) {
            return null;
        }

        return $text[$position] === $specifier ? [$fields, $position + 1] : null;
    }

    /**
     * Stores the number a specifier read, or answers null when it is out of the range of the specifier.
     *
     * @param array<string, int|string> $fields
     * @return array<string, int|string>|null
     */
    public function number(string $specifier, string $digits, array $fields): ?array
    {
        $number = (int) $digits;
        [$low, $high] = self::RANGES[$specifier] ?? [0, PHP_INT_MAX];
        if ($number < $low || $number > $high) {
            return null;
        }

        return array_replace($fields, $this->stored($specifier, $digits, $number));
    }

    /**
     * Answers the fields a number specifier stores for the number it read.
     *
     * @return array<string, int>
     */
    public function stored(string $specifier, string $digits, int $number): array
    {
        return match ($specifier) {
            'd', 'e', 'D' => ['day' => $number],
            'm', 'c' => ['month' => $number],
            'Y' => ['year' => strlen($digits) <= 2 ? $this->century($number) : $number],
            'y' => ['year' => $this->century($number)],
            'H', 'k' => ['hour' => $number],
            'h', 'I', 'l' => ['hour' => $number % 12, 'twelve' => 1],
            'i' => ['minute' => $number],
            's', 'S' => ['second' => $number],
            'f' => ['micro' => (int) str_pad($digits, 6, '0')],
            'j' => ['ordinal' => $number],
            'w' => ['weekday' => $number],
            'U', 'u', 'V', 'v' => ['week' => $number, 'monday' => (int) in_array($specifier, ['u', 'v'], true), 'strict' => (int) in_array($specifier, ['V', 'v'], true)],
            default => ['weekYear' => $number],
        };
    }

    /**
     * Answers the year a year of two digits names: 2000-2069 or 1970-1999.
     */
    public function century(int $year): int
    {
        return $year < 70 ? $year + 2000 : $year + 1900;
    }

    /**
     * Turns the fields read into year, month, day, hour, minute, second and microsecond, or answers null when they name no date.
     *
     * @param array<string, int|string> $fields
     * @return array{int, int, int, int, int, int, int}|null
     */
    public function resolve(array $fields): ?array
    {
        $year = (int) ($fields['year'] ?? 0);
        $month = (int) ($fields['month'] ?? 0);
        $day = (int) ($fields['day'] ?? 0);
        $hour = (int) ($fields['hour'] ?? 0) + (($fields['meridian'] ?? '') === 'pm' ? 12 : 0);
        if (isset($fields['ordinal'])) {
            [$year, $month, $day] = Calendar::date(Readings::dayNumber($year, 1, 1) + (int) $fields['ordinal'] - 1);
        }
        if (isset($fields['week'], $fields['weekday'])) {
            $number = $this->weekly($fields, $year);
            if ($number <= 365) {
                return null;
            }
            [$year, $month, $day] = Calendar::date($number);
        } elseif (($fields['strict'] ?? 0) === 1) {
            [$year, $month, $day] = [0, 0, 0];
        }

        return [$year, $month, $day, $hour, (int) ($fields['minute'] ?? 0), (int) ($fields['second'] ?? 0), (int) ($fields['micro'] ?? 0)];
    }

    /**
     * Answers the day number of the day of the week a week specifier and a day of the week name, in the year read or, for %V and %v, the year of the week.
     *
     * @param array<string, int|string> $fields
     */
    public function weekly(array $fields, int $year): int
    {
        $base = ($fields['strict'] ?? 0) === 1 ? (int) ($fields['weekYear'] ?? 0) : $year;
        $first = Readings::dayNumber($base, 1, 1);
        $monday = (int) ($fields['monday'] ?? 0) === 1;
        $offset = (($first + ($monday ? 5 : 6)) % 7 + 7) % 7;
        $start = $monday ? ($offset >= 4 ? $first + 7 - $offset : $first - $offset) : ($offset === 0 ? $first : $first + 7 - $offset);
        $weekday = (int) ($fields['weekday'] ?? 0);

        return $start + ((int) ($fields['week'] ?? 0) - 1) * 7 + ($monday ? ($weekday + 6) % 7 : $weekday);
    }

    /**
     * Tells whether parts make a value the result takes: a date that exists, with zero parts only as sql_mode allows.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function accepted(array $parts, Domain $result, Context $context): bool
    {
        [$year, $month, $day] = $parts;
        if ($month > 12 || $day > 31 || $year > 9999) {
            return false;
        }
        if ($result->kind === Kind::Time && $year === 0 && $month === 0 && $day === 0) {
            return true;
        }
        $modes = $context->modes;
        if (($modes->has('NO_ZERO_DATE') && ($year === 0 || $month === 0 || $day === 0)) || ($modes->has('NO_ZERO_IN_DATE') && ($month === 0 || $day === 0))) {
            return false;
        }

        return $month === 0 || $day === 0 || Temporal::valid($year, $month, $day);
    }

    /**
     * Writes parts in the type of the result.
     *
     * @param array{int, int, int, int, int, int, int} $parts
     */
    public function written(array $parts, Domain $result): string
    {
        return match ($result->kind) {
            Kind::Date => Temporal::date($parts[0], $parts[1], $parts[2]),
            Kind::Time => Temporal::time(false, $parts[2] * 24 + $parts[3], $parts[4], $parts[5], $parts[6], $result->decimals),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $result->decimals),
        };
    }
}
