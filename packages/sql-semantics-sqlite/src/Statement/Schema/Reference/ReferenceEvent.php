<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * The change of a parent key a foreign key action is written for.
 *
 * The grammar also accepts ON INSERT; SQLite reads it and attaches no action to it.
 * Source: https://sqlite.org/foreignkeys.html#fk_actions.
 *
 * @visibility SqlSemantics
 */
enum ReferenceEvent: string
{
    case Insert = 'INSERT';
    case Delete = 'DELETE';
    case Update = 'UPDATE';
}
