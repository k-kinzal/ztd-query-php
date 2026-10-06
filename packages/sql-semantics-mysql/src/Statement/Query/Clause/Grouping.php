<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A GROUP BY clause: the grouping expressions and the super-aggregate modifier.
 *
 * An item may carry a direction in the 5.x grammars, where GROUP BY also
 * sorted the groups. The selection that holds the clause derives the items.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html,
 * https://dev.mysql.com/doc/refman/5.7/en/select.html.
 *
 * @visibility public
 * @example Reading the grouping expressions
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a, b FROM t GROUP BY a, b');
 *     count($query->statement->groupBy->items) // => 2
 * @example Refusing an empty grouping
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Grouping implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<OrderItem> The grouping expressions in written order
     */
    public readonly array $items;

    /**
     * @param list<OrderItem> $items The grouping expressions in written order; at least one
     * @param GroupingModifier|null $modifier The super-aggregate modifier
     */
    public function __construct(array $items, public readonly ?GroupingModifier $modifier = null)
    {
        $this->items = Check::listOf($items, OrderItem::class, 'GROUP BY holds at least one grouping expression.', 1);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('GROUP', 'BY');
        if ($this->modifier === GroupingModifier::Rollup || $this->modifier === GroupingModifier::Cube) {
            $out->keyword($this->modifier === GroupingModifier::Rollup ? 'ROLLUP' : 'CUBE')->symbol('(')->list($this->items)->symbol(')');

            return;
        }
        $out->list($this->items);
        if ($this->modifier !== null) {
            $out->keyword('WITH', $this->modifier === GroupingModifier::WithRollup ? 'ROLLUP' : 'CUBE');
        }
    }
}
