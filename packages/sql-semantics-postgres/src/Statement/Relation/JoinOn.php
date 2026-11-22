<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * ON condition: the join condition of a qualified join.
 *
 * The condition sees the two sides of its join and the enclosing query, not
 * the other FROM items.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN.
 *
 * @visibility SqlSemantics
 */
final class JoinOn implements Node
{
    use Snapshot;

    /**
     * @param Scalar $condition The join condition
     */
    public function __construct(public readonly Scalar $condition)
    {
    }

    /**
     * Writes ON and the condition.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON')->node($this->condition);
    }
}
