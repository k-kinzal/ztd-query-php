<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The name SQLite picks at random for a column of a subquery whose name repeats earlier names too often.
 *
 * Rule: SQLITE-RELATION-NAME-001. SQLite tells repeated column names of a
 * subquery, a common table or a view apart with the suffixes `:1` to `:4`;
 * when all of them are taken it appends random digits. The name of such a
 * column is decided only when the statement runs, so a lookup that could
 * only match that column depends on this choice.
 * Source: `sqlite3ColumnsFromExprList()` in select.c of release 3.47.2,
 * https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading why a column of a derived table cannot be decided by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 AS a, 2 AS a, 3 AS a, 4 AS a, 5 AS a, 6 AS a)', []);
 *     $query->facts->scalar($query->statement->columns[0]->expression)->resolution->missing[0]->describe() // => 'the name SQLite picks at random for a column whose name repeats five earlier names'
 */
final class RandomColumnName implements MissingInput
{
    use Snapshot;

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the name SQLite picks at random for a column whose name repeats five earlier names';
    }
}
