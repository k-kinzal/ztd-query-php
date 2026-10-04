<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

/**
 * The part of an object that ALTER ... RENAME renames instead of the object itself.
 *
 * A column of a table, view, materialized view or foreign table; a
 * constraint of a table or domain; an attribute of a composite type.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Spelling the attribute part
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart::Attribute->value // => 'ATTRIBUTE'
 */
enum RenamedPart: string
{
    case Column = 'COLUMN';
    case Constraint = 'CONSTRAINT';
    case Attribute = 'ATTRIBUTE';
}
