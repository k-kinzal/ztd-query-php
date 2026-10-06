<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The action DO NOTHING of a MERGE WHEN clause: the row is skipped.
 *
 * Mirrors a `MergeWhenClause` with `CMD_NOTHING`.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading a skip action
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN MATCHED THEN DO NOTHING');
 *     $merge->statement->clauses[0]->action instanceof \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeNothing // => true
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
