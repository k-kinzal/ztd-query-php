<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The action DELETE of a MERGE WHEN clause: the target row is deleted.
 *
 * Mirrors a `MergeWhenClause` with `CMD_DELETE`.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading a delete action
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN MATCHED THEN DELETE');
 *     $merge->clauses[0]->action instanceof \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeDelete // => true
 */
final class MergeDelete implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    /**
 * Constructs a SQL occurrence with optional publication information.
 */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes DELETE.
     */
    public function render(Output $out): void
    {
        $out->keyword('DELETE');
    }
}
