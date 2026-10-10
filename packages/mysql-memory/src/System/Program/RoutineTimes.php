<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;

/**
 * Routine creation and modification instants, stored in UTC and displayed in the session time zone.
 *
 * SHOW ROUTINE STATUS and INFORMATION_SCHEMA.ROUTINES display the same instants in the current
 * session zone, including on MySQL 5.6 where the protocol calls the columns DATETIME.
 * Verified through SQL observations on MySQL 5.6.51, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class RoutineTimes
{
    /**
     * Formats a UTC dictionary timestamp in the reading session's time zone.
     */
    public static function local(string $utc, Context $context): string
    {
        $parts = Temporal::parseDateTime($utc);
        if ($parts === null) {
            return $utc;
        }
        $instant = Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]);

        return Temporal::dateTime(...[...$context->local((float) $instant), 0]);
    }
}
