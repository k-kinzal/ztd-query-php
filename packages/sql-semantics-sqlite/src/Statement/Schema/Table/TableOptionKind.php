<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

/**
 * The table options SQLite knows.
 *
 * Source: https://sqlite.org/withoutrowid.html, https://sqlite.org/stricttables.html.
 *
 * @visibility public
 * @example Reading the kind of a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY) WITHOUT ROWID');
 *     $create->statement->options[0]->kind() // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind::WithoutRowid
 */
enum TableOptionKind
{
    case WithoutRowid;
    case Strict;
}
