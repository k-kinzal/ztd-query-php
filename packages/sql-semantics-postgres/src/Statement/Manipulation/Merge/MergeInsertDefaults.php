<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The action INSERT DEFAULT VALUES of a MERGE WHEN clause: a row of column defaults is inserted.
 *
 * Mirrors a `MergeWhenClause` with `CMD_INSERT` and no values.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility SqlSemantics
 */
final class MergeInsertDefaults implements Node
{
    use Snapshot;

    /**
     * Writes INSERT DEFAULT VALUES.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT', 'DEFAULT', 'VALUES');
    }
}
