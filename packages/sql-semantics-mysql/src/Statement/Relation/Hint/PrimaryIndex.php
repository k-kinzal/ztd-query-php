<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The primary key index named by the PRIMARY keyword in an index list.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility public
 * @example Reading the primary key in an index hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t USE INDEX (PRIMARY, i)');
 *     $query->statement->from->indexHints[0]->indexes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex // => true
 */
final class PrimaryIndex implements Node
{
    use Snapshot;

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('PRIMARY');
    }
}
