<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

/**
 * The ways a table definition can use generated columns wrongly.
 *
 * Source: https://sqlite.org/gencol.html#limitations.
 *
 * @visibility public
 * @example Naming the flaw of a generated column with a default
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b AS (a) DEFAULT 1)');
 *     $create->facts->diagnostics[0]->flaw // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnFlaw::WithDefault
 */
enum GeneratedColumnFlaw: string
{
    case WithDefault = 'A generated column cannot have a DEFAULT value.';
    case InPrimaryKey = 'A generated column cannot be part of the PRIMARY KEY.';
    case NoPlainColumn = 'A table must have at least one column that is not generated.';
}
