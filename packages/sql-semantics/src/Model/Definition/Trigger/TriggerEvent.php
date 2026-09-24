<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

/**
 * A table change that can fire a PostgreSQL relation trigger.
 * @visibility public
 * @example Naming a relation trigger event
 *     \SqlSemantics\Model\Definition\Trigger\TriggerEvent::Truncate->value // => 'TRUNCATE'
 */
enum TriggerEvent: string
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case Truncate = 'TRUNCATE';
}
