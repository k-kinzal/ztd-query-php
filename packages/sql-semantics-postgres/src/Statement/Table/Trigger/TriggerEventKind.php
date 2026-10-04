<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

/**
 * The events a trigger can fire on.
 *
 * Mirrors the `TRIGGER_TYPE_*` event bits.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility public
 * @example Reading the event of a trigger
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind::Truncate->value // => 'TRUNCATE'
 */
enum TriggerEventKind: string
{
    case Insert = 'INSERT';
    case Delete = 'DELETE';
    case Update = 'UPDATE';
    case Truncate = 'TRUNCATE';
}
