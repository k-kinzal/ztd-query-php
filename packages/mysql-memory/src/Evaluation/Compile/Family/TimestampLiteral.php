<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Session\SqlModes;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use MySqlMemory\Value\Zone;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Literal\Timestamp;

/**
 * Applies the session's calendar modes and time zone to a TIMESTAMP operand.
 *
 * Displacements are supported from MySQL 8.0.19. Native SQL observations show
 * that carrying rounded fractional seconds discards the displacement before
 * conversion. This operates on decoded literal components, never source SQL.
 *
 * @visibility MySqlMemory
 */
final class TimestampLiteral
{
    /**
     * Formats a refused literal, distinguishing calendar conversion from a malformed representation.
     */
    public static function error(string $text, SqlModes $modes): \MySqlMemory\Error\SqlError
    {
        $parts = Timestamp::parts($text);
        $local = Timestamp::value($text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'), $modes->has('ALLOW_INVALID_DATES'));
        if ($parts !== null && $local !== null && $parts[7] !== null && !in_array($modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && ($parts[1] === 0 || $parts[2] === 0)) {
            $clock = Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], Temporal::micro($parts[6], false), min(6, strlen($parts[6])));
            $offset = ($parts[7] < 0 ? '-' : '+') . intdiv(abs($parts[7]), 3600) . sprintf(':%02d', intdiv(abs($parts[7]) % 3600, 60));

            return \MySqlMemory\Error\Family\DataError::TruncatedWrongValue->error('temporal', $clock . $offset);
        }

        return \MySqlMemory\Error\Family\DataError::WrongValue->error('DATETIME', substr(explode("\0", $text, 2)[0], 0, 128));
    }

    /**
     * Answers the DATETIME value, or null for a representation refused in this session.
     *
     * @throws \MySqlMemory\Error\SqlError When a displacement moves outside the explicit zone's calendar range
     */
    public static function value(string $text, int $decimals, SqlModes $modes, Zone $zone): ?string
    {
        $parts = Timestamp::parts($text);
        $local = Timestamp::value($text, $decimals, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'), $modes->has('ALLOW_INVALID_DATES'));
        if ($parts === null || $local === null || $parts[7] === null) {
            return $local;
        }
        if (in_array($modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        if (Temporal::micro($parts[6], false) >= 1000000) {
            return $local;
        }
        if ($parts[1] === 0 || $parts[2] === 0) {
            return null;
        }
        $instant = Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]) - $parts[7];
        $moment = self::moment($zone->local($instant));
        if ($zone->name === 'SYSTEM' && $moment[0] <= 0) {
            return Temporal::dateTime(0, 0, 0, 0, 0, 0, 0, $decimals);
        }
        if ($zone->name !== 'SYSTEM' && ($moment[0] < 0 || $moment[0] > 9999)) {
            $shown = Temporal::dateTime($moment[0] < 0 ? 95 : $moment[0] % 10000, $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], Temporal::micro($parts[6], false), $decimals);

            throw \MySqlMemory\Error\Family\DataError::TruncatedWrongValue->error('temporal', $shown);
        }

        return Temporal::dateTime($moment[0] % 10000, $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], Temporal::micro($parts[6], false), $decimals);
    }

    /**
     * Keeps year-zero calendar fields which FROM_DAYS deliberately collapses to the zero date.
     *
     * A displacement can cross the adjacent year boundary. Its out-of-range
     * display is observed on native SQL; explicit zones reject it while SYSTEM
     * has a separate conversion range.
     *
     * @return array{int, int, int, int, int, int}
     */
    public static function moment(int $seconds): array
    {
        $rest = ($seconds % 86400 + 86400) % 86400;
        $days = intdiv($seconds - $rest, 86400) + 719528;
        if ($days > 365) {
            return Calendar::moment($seconds);
        }
        if ($days <= 0) {
            return [-1, 12, 31 + $days, intdiv($rest, 3600), intdiv($rest % 3600, 60), $rest % 60];
        }
        $month = 1;
        while ($days > Calendar::monthLength(1, $month)) {
            $days -= Calendar::monthLength(1, $month);
            $month++;
        }

        return [0, $month, $days, intdiv($rest, 3600), intdiv($rest % 3600, 60), $rest % 60];
    }
}
