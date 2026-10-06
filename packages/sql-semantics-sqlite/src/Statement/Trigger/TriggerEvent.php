<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

/**
 * The change that fires a trigger.
 *
 * Source: https://sqlite.org/lang_createtrigger.html.
 *
 * @visibility public
 * @example Reading the event of a trigger
 *     $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TRIGGER r AFTER UPDATE OF a ON t BEGIN SELECT 1; END');
 *     $trigger->statement->event // => \SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent::Update
 */
enum TriggerEvent: string
{
    case Delete = 'DELETE';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
}
