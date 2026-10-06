<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

/**
 * The change of a referenced row a referential action answers.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility public
 * @example Reading the event of a referential action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int REFERENCES u ON DELETE CASCADE)');
 *     $create->statement->definition->elements[0]->qualifiers[0]->actions[0]->event // => \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete
 */
enum ReferenceEvent: string
{
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
