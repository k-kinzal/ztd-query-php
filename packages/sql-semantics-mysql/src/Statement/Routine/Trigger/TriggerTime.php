<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

/**
 * When a trigger runs relative to the row change: BEFORE or AFTER.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility public
 * @example Reading the keyword of a time
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime::Before->value // => 'BEFORE'
 */
enum TriggerTime: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
}
