<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A view whose column list and query disagree in length (ER_VIEW_WRONG_LIST).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html.
 *
 * @visibility public
 * @example Reading the problem
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE VIEW v (a, b) AS SELECT 1')->facts->diagnostics[0]->returned // => 1
 */
final class ViewColumnCount implements Diagnostic
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
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'View\'s SELECT and view\'s field list have different column counts (' . $this->listed . ' and ' . $this->returned . ').';
    }
}
