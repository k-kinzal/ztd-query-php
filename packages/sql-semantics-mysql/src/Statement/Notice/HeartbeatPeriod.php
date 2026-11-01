<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

/**
 * A heartbeat period accepted with a notice while its option is read.
 *
 * Each written option can warn, including an option superseded later in the
 * same statement. The timeout is a resolution input; the warning records the
 * resulting condition rather than asking a consumer to evaluate it again.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Reading the warning number for a sub-millisecond period
 *     (new \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod(\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::BelowMinimum))->code() // => 1703
 */
final class HeartbeatPeriod implements CodedWarning
{
    use \SqlSemantics\Statement\Snapshot;

    /**
     * @param HeartbeatCondition $condition The condition already resolved for this occurrence
     */
    public function __construct(public readonly HeartbeatCondition $condition)
    {
    }

    /**
     * Returns the native warning number.
     */
    public function code(): int
    {
        return $this->condition === HeartbeatCondition::BelowMinimum ? 1703 : 1704;
    }

    /**
     * Describes the accepted period using the release's timeout vocabulary.
     */
    public function message(): string
    {
        if ($this->condition === HeartbeatCondition::BelowMinimum) {
            return 'The requested value for the heartbeat period is less than 1 millisecond. The value is reset to 0, meaning that heartbeating will effectively be disabled.';
        }
        $name = $this->condition === HeartbeatCondition::AboveSlaveTimeout ? 'slave_net_timeout' : 'replica_net_timeout';

        return 'The requested value for the heartbeat period exceeds the value of `' . $name . "' seconds. A sensible value for the period should be less than the timeout.";
    }
}
