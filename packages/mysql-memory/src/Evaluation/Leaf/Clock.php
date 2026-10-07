<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Clock as ClockKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The current date or time: the instant the statement started, or the current instant for SYSDATE().
 *
 * The session time zone is the zone of the server (SYSTEM); the UTC clocks read UTC.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_now.
 *
 * @visibility MySqlMemory
 */
final class Clock implements Evaluable
{
    /**
     * @param ClockKind $clock The clock read
     * @param Domain $domain The domain of the value
     */
    public function __construct(public readonly ClockKind $clock, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the clock.
     */
    #[Override]
    public function evaluate(Frame $frame): string
    {
        $instant = $this->clock === ClockKind::SystemDate ? microtime(true) : $frame->context->started;
        $utc = in_array($this->clock, [ClockKind::UtcDate, ClockKind::UtcTime, ClockKind::UtcTimestamp], true);
        $seconds = (int) floor($instant);
        $micro = (int) round(($instant - $seconds) * 1000000);
        $parts = array_map('intval', explode(' ', $utc ? gmdate('Y m d H i s', $seconds) : date('Y m d H i s', $seconds)));
        $unit = 10 ** (6 - $this->domain->decimals);
        $micro = intdiv(min($micro, 999999), $unit) * $unit;

        return match ($this->domain->kind) {
            Kind::Date => Temporal::date($parts[0], $parts[1], $parts[2]),
            Kind::Time => Temporal::time(false, $parts[3], $parts[4], $parts[5], $micro, $this->domain->decimals),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $micro, $this->domain->decimals),
        };
    }
}
