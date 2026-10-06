<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A table definition that states its primary key in a way SQLite rejects.
 *
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key.
 *
 * @visibility public
 * @example Reporting AUTOINCREMENT on a column that is no integer primary key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a TEXT PRIMARY KEY AUTOINCREMENT)');
 *     $create->facts->diagnostics[0]->message() // => 'AUTOINCREMENT is only allowed on an INTEGER PRIMARY KEY.'
 */
final class PrimaryKeyProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param PrimaryKeyFlaw $flaw What is wrong with the primary key
     */
    public function __construct(public readonly PrimaryKeyFlaw $flaw)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->flaw->value;
    }
}
