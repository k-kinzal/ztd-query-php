<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Temporal;
use MySqlMemory\Value\Zone;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions of instants and time zones: UNIX_TIMESTAMP, FROM_UNIXTIME and CONVERT_TZ.
 *
 * Instants run from 1970-01-01 00:00:01 to 3001-01-18 23:59:59.999999 UTC. UNIX_TIMESTAMP reads a
 * date and time in the time zone of the session and is 0 outside that range; FROM_UNIXTIME
 * writes an instant in the time zone of the session and is NULL outside it. CONVERT_TZ converts
 * only a value whose instant is in that range, and answers any other unchanged; an unknown zone
 * makes it NULL (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_unix-timestamp,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_convert-tz.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Epochs
{
    /**
     * The last second of the range of instants.
     */
    public const LATEST = 32536771199;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('UNIX_TIMESTAMP', 0, 1, fn (Frame $f, array $a, Domain $r): int|string|null => $this->unix($f, $a, $r)),
            new Routine('FROM_UNIXTIME', 1, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->fromUnix($f, $a, $r)),
            new Routine('CONVERT_TZ', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->convert($f, $a, $r)),
        ];
    }

    /**
     * UNIX_TIMESTAMP: the seconds since 1970-01-01 00:00:00 UTC of the statement, or of a date and time of the session's zone.
     *
     * @param list<Evaluable> $arguments
     */
    public function unix(Frame $frame, array $arguments, Domain $result): int|string|null
    {
        if ($arguments === []) {
            return (int) floor($frame->context->started);
        }
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $parts = (new Readings())->value($value, $arguments[0]->domain(), Zeros::Dated, $frame->context);
        $seconds = 0;
        $micro = 0;
        if ($parts !== null && $parts[1] !== 0 && $parts[2] !== 0) {
            $seconds = $frame->context->zone()->instant(Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]));
            $micro = $parts[6];
            if ($seconds < 1 || $seconds > self::LATEST) {
                [$seconds, $micro] = [0, 0];
            }
        }
        if ($result->kind !== Kind::Decimal) {
            return $seconds;
        }

        return Decimal::round($seconds . '.' . sprintf('%06d', $micro), $result->decimals);
    }

    /**
     * FROM_UNIXTIME: the date and time of an instant in the time zone of the session, written by a format when one is given.
     *
     * @param list<Evaluable> $arguments
     */
    public function fromUnix(Frame $frame, array $arguments, Domain $result): ?string
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $decimals = isset($arguments[1]) ? 6 : $result->decimals;
        $number = Decimal::round((string) Convert::toDecimal($value, $arguments[0]->domain(), $frame->context), $decimals);
        if (Decimal::compare($number, '0') < 0 || Decimal::compare($number, (string) (self::LATEST + 1)) >= 0) {
            return null;
        }
        $seconds = (int) Decimal::truncate($number, 0);
        $micro = (int) Decimal::multiply(Decimal::subtract($number, (string) $seconds), '1000000');
        $moment = Calendar::moment($frame->context->zone()->local($seconds));
        if (!isset($arguments[1])) {
            return Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $micro, $result->decimals);
        }
        $format = $arguments[1]->evaluate($frame);

        return $format === null ? null : (new Formatting())->write([...$moment, $micro], (string) $format, false, $frame);
    }

    /**
     * CONVERT_TZ: a date and time moved from one time zone to another.
     *
     * @param list<Evaluable> $arguments
     */
    public function convert(Frame $frame, array $arguments, Domain $result): ?string
    {
        $parts = (new Readings())->moment($frame, $arguments[0], Zeros::Refused);
        if ($parts === null) {
            return null;
        }
        $from = $arguments[1]->evaluate($frame);
        $to = $arguments[2]->evaluate($frame);
        $source = $from === null ? null : Zone::named((string) $from);
        $target = $to === null ? null : Zone::named((string) $to);
        if ($source === null || $target === null) {
            return null;
        }
        $micro = Temporal::scale($parts[6], $result->decimals, $frame->context->modes->has('TIME_TRUNCATE_FRACTIONAL'));
        $instant = $source->instant(Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]));
        if ($instant < 1 || $instant > self::LATEST) {
            return Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $micro, $result->decimals);
        }
        $moment = Calendar::moment($target->local($instant));

        return Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $micro, $result->decimals);
    }
}
