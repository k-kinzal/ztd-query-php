<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

/**
 * The time at which a trigger body runs.
 *
 * @visibility public
 * @example Selecting the phase after a watched mutation
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerTiming::After->value // => 'AFTER'
 */
enum TriggerTiming: string
{
    case After = 'AFTER';
    case Before = 'BEFORE';
    case InsteadOf = 'INSTEAD OF';
}
