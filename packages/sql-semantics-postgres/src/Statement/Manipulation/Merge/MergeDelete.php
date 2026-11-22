<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The action DELETE of a MERGE WHEN clause: the target row is deleted.
 *
 * Mirrors a `MergeWhenClause` with `CMD_DELETE`.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility SqlSemantics
 */
final class MergeDelete implements Node
{
    use Snapshot;

    /**
     * Writes DELETE.
     */
    public function render(Output $out): void
    {
        $out->keyword('DELETE');
    }
}
