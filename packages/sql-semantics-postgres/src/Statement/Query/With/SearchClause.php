<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * SEARCH DEPTH FIRST or BREADTH FIRST BY columns SET column: an ordering column added to a recursive query.
 *
 * Mirrors PostgreSQL's `CTESearchClause`. The sequence column is appended to
 * the columns of the common table; the BY columns name columns of it.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-SEARCH.
 *
 * @visibility SqlSemantics
 * @example Reading a search clause
 *     $clause = new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchClause(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::Depth, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('id')], new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ordercol'));
 *     [$clause->order->value, $clause->sequence->value] // => ['DEPTH', 'ordercol']
 */
final class SearchClause implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The columns the order follows
     */
    public readonly array $columns;

    /**
     * @param SearchOrder $order Depth-first or breadth-first
     * @param list<Name> $columns The columns the order follows; at least one
     * @param Name $sequence The name of the added ordering column
     */
    public function __construct(public readonly SearchOrder $order, array $columns, public readonly Name $sequence)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A SEARCH clause names at least one column.', 1);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('SEARCH', $this->order->value, 'FIRST', 'BY');
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
        $out->keyword('SET')->name($this->sequence, NameUse::Column);
    }
}
