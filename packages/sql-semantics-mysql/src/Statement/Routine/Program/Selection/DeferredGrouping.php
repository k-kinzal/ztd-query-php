<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored GROUP BY retaining at least one invocation-time ordering operand.
 *
 * Ordinary items and the super-aggregate modifier keep their distinct roles.
 * The enclosing selection determines their name-resolution scope.
 *
 * @visibility public
 * @example Refusing a grouping without a deferred operand
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredGrouping implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<OrderItem|DeferredOrderItem> The grouping expressions in written order
     */
    public readonly array $items;

    /**
     * @param list<OrderItem|DeferredOrderItem> $items The grouping expressions in written order; at least one
     * @param GroupingModifier|null $modifier The super-aggregate modifier
     */
    public function __construct(array $items, public readonly ?GroupingModifier $modifier = null)
    {
        $list = [];
        $deferred = false;
        foreach (Check::listOf($items, Node::class, 'GROUP BY holds at least one item.', 1) as $item) {
            Check::input($item instanceof OrderItem || $item instanceof DeferredOrderItem, 'A stored grouping retains scalar, ordinal or deferred ordering items.');
            $list[] = $item;
            $deferred = $deferred || $item instanceof DeferredOrderItem;
        }
        Check::input($deferred, 'A deferred grouping retains at least one deferred item.');
        $this->items = $list;
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
