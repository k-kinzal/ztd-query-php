<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

/**
 * The change that fires a trigger.
 *
 * Source: https://sqlite.org/lang_createtrigger.html.
 *
 * @visibility SqlSemantics
 */
enum TriggerEvent: string
{
    case Delete = 'DELETE';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
}
