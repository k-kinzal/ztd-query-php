<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * OFFSET start: the number of rows skipped before rows are returned.
 *
 * The ROW or ROWS written after the start in the SQL-standard spelling are
 * noise words. Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility public
 * @example Reading an offset
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 OFFSET 3 ROWS');
 *     $query->toString() // => 'SELECT 1 OFFSET 3'
 */
final class Offset implements Node
{
    use Snapshot;

    /**
     * @param Scalar $start The number of rows to skip
     */
    public function __construct(public readonly Scalar $start)
    {
    }

    /**
     * Writes OFFSET and the start.
     */
    public function render(Output $out): void
    {
        $out->keyword('OFFSET')->node($this->start);
    }
}
