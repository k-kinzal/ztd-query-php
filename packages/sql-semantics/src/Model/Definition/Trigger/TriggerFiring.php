<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

/**
 * Selects the replication contexts in which a PostgreSQL trigger fires.
 * @visibility public
 * @example Enabling a trigger for every replication role
 *     \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Always->value // => 'ENABLE ALWAYS'
 */
enum TriggerFiring: string
{
    case Origin = 'ENABLE';
    case Replica = 'ENABLE REPLICA';
    case Always = 'ENABLE ALWAYS';
    case Disabled = 'DISABLE';
}
