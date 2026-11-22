<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

/**
 * The ways a table definition can use generated columns wrongly.
 *
 * Source: https://sqlite.org/gencol.html#limitations.
 *
 * @visibility SqlSemantics
 */
enum GeneratedColumnFlaw: string
{
    case WithDefault = 'A generated column cannot have a DEFAULT value.';
    case InPrimaryKey = 'A generated column cannot be part of the PRIMARY KEY.';
    case NoPlainColumn = 'A table must have at least one column that is not generated.';
}
