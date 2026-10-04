<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

/**
 * The kind of row change that activates a trigger: INSERT, UPDATE or DELETE.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility public
 * @example Reading the keyword of an event
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent::Update->value // => 'UPDATE'
 */
enum TriggerEvent: string
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
