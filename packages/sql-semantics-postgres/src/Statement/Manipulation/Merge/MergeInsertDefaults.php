<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The action INSERT DEFAULT VALUES of a MERGE WHEN clause: a row of column defaults is inserted.
 *
 * Mirrors a `MergeWhenClause` with `CMD_INSERT` and no values.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading an insert of the defaults
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN NOT MATCHED THEN INSERT DEFAULT VALUES');
 *     $merge->clauses[0]->action instanceof \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsertDefaults // => true
 */
final class MergeInsertDefaults implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    /**
 * Constructs a SQL occurrence with optional publication information.
 */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes INSERT DEFAULT VALUES.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT', 'DEFAULT', 'VALUES');
    }
}
