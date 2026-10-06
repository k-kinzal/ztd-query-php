<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

/**
 * The ways a table definition can state its primary key wrongly.
 *
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/autoinc.html, https://sqlite.org/withoutrowid.html.
 *
 * @visibility public
 * @example Naming the flaw of a definition with two primary keys
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY, b PRIMARY KEY)');
 *     $create->facts->diagnostics[0]->flaw // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw::Repeated
 */
enum PrimaryKeyFlaw: string
{
    case Repeated = 'The table has more than one primary key.';
    case MissingWithoutRowid = 'A WITHOUT ROWID table needs a primary key.';
    case ExpressionTerm = 'A primary key or unique constraint contains an expression that is not a column name.';
    case AutoincrementNotIntegerKey = 'AUTOINCREMENT is only allowed on an INTEGER PRIMARY KEY.';
    case AutoincrementWithoutRowid = 'AUTOINCREMENT is not allowed on a WITHOUT ROWID table.';
}
