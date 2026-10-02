<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

/**
 * When a trigger runs relative to the change that fires it.
 *
 * Source: https://sqlite.org/lang_createtrigger.html.
 *
 * @visibility public
 * @example Reading the timing of a trigger
 *     $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TRIGGER r INSTEAD OF INSERT ON v BEGIN SELECT 1; END');
 *     $trigger->statement->timing // => \SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming::InsteadOf
 */
enum TriggerTiming: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
    case InsteadOf = 'INSTEAD OF';
}
