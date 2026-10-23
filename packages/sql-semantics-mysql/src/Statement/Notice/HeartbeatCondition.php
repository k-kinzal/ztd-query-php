<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

/**
 * The three native heartbeat conditions, including the release-specific timeout name.
 *
 * @visibility public
 * @example Identifying a period below the minimum
 *     \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::BelowMinimum->name // => 'BelowMinimum'
 */
enum HeartbeatCondition
{
    case BelowMinimum;
    case AboveSlaveTimeout;
    case AboveReplicaTimeout;
}
