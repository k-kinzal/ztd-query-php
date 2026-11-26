<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

/**
 * The watched kind of row mutation.
 *
 * @visibility public
 * @example Watching a row insertion
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent::Insert->value // => 'INSERT'
 */
enum TriggerEvent: string
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
