<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Clock as ClockKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The current date or time: the instant the statement started, or the current instant for SYSDATE().
 *
 * The clocks read the time zone of the session (time_zone); the UTC clocks read UTC. The instant
 * is the `timestamp` of the session when it is set; SYSDATE() always reads the current instant.
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
        $instant = $this->clock === ClockKind::SystemDate ? $frame->context->variables->instance->registry->threads->now() : $frame->context->started;
        $utc = in_array($this->clock, [ClockKind::UtcDate, ClockKind::UtcTime, ClockKind::UtcTimestamp], true);
        $seconds = (int) floor($instant);
        $parts = $utc ? [...Calendar::moment($seconds), min(999999, (int) round(($instant - $seconds) * 1000000))] : $frame->context->local($instant);
        $unit = 10 ** (6 - $this->domain->decimals);
        $micro = intdiv($parts[6], $unit) * $unit;

        return match ($this->domain->kind) {
            Kind::Date => Temporal::date($parts[0], $parts[1], $parts[2]),
            Kind::Time => Temporal::time(false, $parts[3], $parts[4], $parts[5], $micro, $this->domain->decimals),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $micro, $this->domain->decimals),
        };
    }
}
