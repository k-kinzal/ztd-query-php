<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * ON condition: the join condition of a qualified join.
 *
 * The condition sees the two sides of its join and the enclosing query, not
 * the other FROM items.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN.
 *
 * @visibility public
 * @example Reading a join condition
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM a JOIN b ON TRUE');
 *     $query->statement->from->condition instanceof \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn // => true
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
