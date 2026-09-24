<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

/**
 * Whether a scheduled event runs; DISABLE ON SLAVE and DISABLE ON REPLICA are the same replica-side state.
 * @visibility public
 * @example Reading an event status
 *     \SqlSemantics\Model\Definition\Routine\Stored\EventStatus::DisabledOnReplica->value // => 'DISABLE ON SLAVE'
 */
enum EventStatus: string
{
    case Enabled = 'ENABLE';
    case Disabled = 'DISABLE';
    case DisabledOnReplica = 'DISABLE ON SLAVE';
}
