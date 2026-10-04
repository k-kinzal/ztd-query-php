<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The unqualified `*` of a select list: every column of every table of the FROM clause.
 *
 * MySQL accepts it only as the first item of the list. The selection
 * expands it (MYSQL-STAR-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading a star item
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT * FROM t');
 *     $query->statement->items[0] instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star // => true
 */
final class Star implements SelectItem
{
    use Snapshot;

    /**
     * Writes the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
}
