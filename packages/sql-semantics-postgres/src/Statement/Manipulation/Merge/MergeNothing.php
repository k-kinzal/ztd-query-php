<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The action DO NOTHING of a MERGE WHEN clause: the row is skipped.
 *
 * Mirrors a `MergeWhenClause` with `CMD_NOTHING`.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility SqlSemantics
 */
final class MergeNothing implements Node
{
    use Snapshot;

    /**
     * Writes DO NOTHING.
     */
    public function render(Output $out): void
    {
        $out->keyword('DO', 'NOTHING');
    }
}
