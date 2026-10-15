<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

/**
 * The object role of an empty name refused before a table reference is published.
 * Correlation names and listing filters have separate domains.
 *
 * @visibility public
 * @example The role of an empty table name
 *     \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table->value // => 'table'
 */
enum ObjectNameKind: string
{
    case Database = 'database';
    case Table = 'table';
}
