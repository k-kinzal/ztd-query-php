<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A view whose column list has another length than the result of its query.
 *
 * SQLite reports "expected N columns for 'view' but got M" when the view is used.
 * Source: https://sqlite.org/lang_createview.html.
 *
 * @visibility public
 * @example Reporting a column list that is too short
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE VIEW v (a) AS SELECT 1, 2');
 *     $view->facts->diagnostics[0]->message() // => 'The column list names 1 columns but the query returns 2.'
 */
final class ColumnCountMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $listed The number of names in the column list
     * @param int $returned The number of columns the query returns
     */
    public function __construct(public readonly int $listed, public readonly int $returned)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'The column list names ' . $this->listed . ' columns but the query returns ' . $this->returned . '.';
    }
}
