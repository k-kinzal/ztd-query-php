<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The unqualified `*` of a select list: every column of every table of the FROM clause.
 *
 * MySQL accepts it only as the first item of the list. The selection
 * expands it (MYSQL-STAR-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility SqlSemantics
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
