<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

/**
 * Whether the value of a generated column is computed when read or stored when written.
 *
 * Source: https://sqlite.org/gencol.html#virtual_versus_stored_columns.
 *
 * @visibility SqlSemantics
 */
enum GeneratedStorage
{
    case Virtual;
    case Stored;
}
