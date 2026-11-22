<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

/**
 * When a trigger runs relative to the change that fires it.
 *
 * Source: https://sqlite.org/lang_createtrigger.html.
 *
 * @visibility SqlSemantics
 */
enum TriggerTiming: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
    case InsteadOf = 'INSTEAD OF';
}
