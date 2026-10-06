<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A table option that is neither WITHOUT ROWID nor STRICT.
 *
 * The grammar accepts any name as an option; SQLite compares the written text
 * with `rowid` and `strict` and rejects every other word, a quoted `"rowid"`
 * included, with "unknown table option".
 * Source: https://sqlite.org/syntax/table-options.html.
 *
 * @visibility public
 * @example Reporting an unknown option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a) WITHOUT oid');
 *     $create->facts->diagnostics[0]->message() // => 'Table option oid is unknown.'
 */
final class UnknownTableOption implements Diagnostic
{
    use Snapshot;

    /**
     * @param TableOption $option The option SQLite does not know
     */
    public function __construct(public readonly TableOption $option)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Table option ' . $this->option->word->spelling() . ' is unknown.';
    }
}
