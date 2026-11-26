<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * OFFSET start: the number of rows skipped before rows are returned.
 *
 * The ROW or ROWS written after the start in the SQL-standard spelling are
 * noise words. Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility SqlSemantics
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
