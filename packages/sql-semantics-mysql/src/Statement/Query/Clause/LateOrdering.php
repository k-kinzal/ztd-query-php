<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

/**
 * An ORDER BY or LIMIT that a 5.6 subquery writes after the locking clauses of its query block, or a LIMIT it writes after the block's own LIMIT.
 *
 * The 5.6 grammar lets a query specification of a subquery or a derived
 * table be followed by `opt_union_order_or_limit`. Unless the block already
 * orders or limits its rows, the server attaches that ORDER BY and LIMIT to
 * the block itself (the `order_clause` action adds no query level, and
 * `limit_options` sets the limit of the current block), so they are the
 * block's own clauses written at a later position; a LIMIT written after a
 * LIMIT of the block replaces it. The block that holds the clauses derives
 * them like its own ORDER BY and LIMIT. Source:
 * https://dev.mysql.com/doc/refman/5.6/en/select.html,
 * https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_yacc.yy (`order_clause`, `limit_options`).
 *
 * @visibility SqlSemantics
 * @example Refusing a late ordering without clauses
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering([]) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class LateOrdering implements Node
{
    use Snapshot;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param list<OrderItem> $orderBy The ORDER BY items
     * @param Limit|null $limit The LIMIT clause
     */
    public function __construct(array $orderBy, public readonly ?Limit $limit = null)
    {
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        (new SortScopes())->check($this->orderBy);
        Check::input($this->orderBy !== [] || $limit !== null, 'A late ordering holds an ORDER BY or a LIMIT.');
    }

    /**
     * Writes ORDER BY and LIMIT.
     */
    public function render(Output $out): void
    {
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
