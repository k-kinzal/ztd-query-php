<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * The change of a parent key a foreign key action is written for.
 *
 * The grammar also accepts ON INSERT; SQLite reads it and attaches no action to it.
 * Source: https://sqlite.org/foreignkeys.html#fk_actions.
 *
 * @visibility public
 * @example Reading the event of a foreign key action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent ON DELETE CASCADE)');
 *     $create->statement->columns[0]->constraints[0]->arguments[0]->event // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent::Delete
 */
enum ReferenceEvent: string
{
    case Insert = 'INSERT';
    case Delete = 'DELETE';
    case Update = 'UPDATE';
}
