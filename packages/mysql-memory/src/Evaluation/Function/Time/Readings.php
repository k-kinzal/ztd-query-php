<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads the arguments of the date and time functions: as a date and time, as a time, or as a number.
 *
 * A value that is no date or time is NULL with a warning (ER_TRUNCATED_WRONG_VALUE); a date
 * with zero parts the function does not take (Zeros) is NULL with the same warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-conversion.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Readings
{
    /**
     * Reads an argument as a date and time: year, month, day, hour, minute, second and microsecond, or null.
     *
     * @return array{int, int, int, int, int, int, int}|null
     */
    public function moment(Frame $frame, Evaluable $argument, Zeros $zeros): ?array
    {
        $value = $argument->evaluate($frame);

        return $value === null ? null : $this->value($value, $argument->domain(), $zeros, $frame->context);
    }

    /**
     * Reads a value as a date and time, or answers null.
     *
     * @return array{int, int, int, int, int, int, int}|null
     */
    public function value(int|float|string $value, Domain $domain, Zeros $zeros, Context $context): ?array
    {
        $modes = $context->modes;
        $flags = match ($zeros) {
            Zeros::Modes => null,
            Zeros::Dated => [$modes->has('NO_ZERO_DATE'), false],
            Zeros::Refused, Zeros::Months => [false, false],
        };
        $text = (new Moments())->convert($value, $domain, new Domain(Kind::DateTime, Field::DateTime, 26, 6), $context, $flags);
        $parts = $text === null ? null : Temporal::parseDateTime($text);
        if ($parts === null) {
            return null;
        }
        if (($zeros === Zeros::Refused && ($parts[1] === 0 || $parts[2] === 0)) || ($zeros === Zeros::Months && $parts[1] === 0)) {
            $this->refuse($value, $domain, $context);

            return null;
        }

        return [$parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6]];
    }

    /**
     * Records that a value is no date the function takes: ER_TRUNCATED_WRONG_VALUE, "Incorrect datetime value".
     */
    public function refuse(int|float|string $value, Domain $domain, Context $context): void
    {
        $context->warnMessage(DataError::TruncatedWrongValue, DataError::WrongValue->message('datetime', (string) Convert::toText($value, $domain)));
    }

    /**
     * Reads an argument as a time: whether it is negative, hours, minute, second and microsecond, or null.
     *
     * @return array{bool, int, int, int, int}|null
     */
    public function time(Frame $frame, Evaluable $argument, int $decimals = 6): ?array
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $time = (new Moments())->time($value, $argument->domain(), $decimals, $frame->context);

        return $time === null ? null : Temporal::parseTime($time);
    }

    /**
     * Reads an argument as an integer, or null.
     */
    public function integer(Frame $frame, Evaluable $argument): ?int
    {
        $value = $argument->evaluate($frame);

        return $value === null ? null : Convert::toInteger($value, $argument->domain(), $frame->context);
    }

    /**
     * Answers the day number of a date as the server counts it, which a zero month or day also has: days since 0000-01-01, that day being 1.
     */
    public static function dayNumber(int $year, int $month, int $day): int
    {
        if ($year === 0 && $month === 0) {
            return 0;
        }
        $days = 365 * $year + 31 * ($month - 1) + $day;
        if ($month <= 2) {
            $year--;
        } else {
            $days -= intdiv($month * 4 + 23, 10);
        }

        return $days + intdiv($year, 4) - intdiv((intdiv($year, 100) + 1) * 3, 4);
    }

    /**
     * Answers the day of the week of a day number: 0 for Monday to 6 for Sunday.
     *
     * @return int<0, 6>
     */
    public static function weekday(int $dayNumber): int
    {
        return (($dayNumber + 5) % 7 + 7) % 7;
    }
}
