<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

/**
 * Whether the value of a generated column is computed when read or stored when written.
 *
 * Source: https://sqlite.org/gencol.html#virtual_versus_stored_columns.
 *
 * @visibility public
 * @example Reading the storage of a generated column
 *     $create = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('CREATE TABLE t (a, b AS (a + 1) STORED)');
 *     $create->statement->columns[1]->constraints[0]->storage() // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage::Stored
 */
enum GeneratedStorage
{
    case Virtual;
    case Stored;
}
