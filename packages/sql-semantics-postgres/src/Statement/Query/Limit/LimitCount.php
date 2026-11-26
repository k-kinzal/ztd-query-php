<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * LIMIT count, or LIMIT ALL: the most rows a query returns.
 *
 * LIMIT ALL is the same as omitting the clause and is kept as a null count
 * because it is written. Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility SqlSemantics
 */
final class LimitCount implements Node
{
    use Snapshot;

    /**
     * @param Scalar|null $count The row count; null for ALL
     */
    public function __construct(public readonly ?Scalar $count = null)
    {
    }

    /**
     * Writes LIMIT and the count or ALL.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIMIT');
        if ($this->count === null) {
            $out->keyword('ALL');
        } else {
            $out->node($this->count);
        }
    }
}
