<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Statement\Scalar;

/**
 * Reads stored sort clauses without converting deferred operands into scalars.
 *
 * Ordinary integer items still denote output ordinals and ordinary clauses
 * retain their existing structure. Deferred items preserve complete operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StoredClauses
{
    /**
     * @param Lowering $lowering The input's grammar interpretation
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * @return list<OrderItem|DeferredOrderItem> Complete ordered items
     */
    public function ordering(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        return match ($form->signature) {
            'opt_order_clause:' => [],
            'opt_order_clause: order_clause' => $this->ordering($form->node(0)),
            'order_clause: ORDER_SYM BY order_list' => $this->items($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Retains the modifier and each ordinary or deferred grouping item.
     */
    public function grouping(Node $clause): Grouping|DeferredGrouping|null
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_group_clause:' || $form->signature === 'group_clause:') {
            return null;
        }
        [$position, $modifier] = match ($form->signature) {
            'opt_group_clause: GROUP_SYM BY group_list olap_opt', 'group_clause: GROUP_SYM BY group_list olap_opt' => [2, (new ClauseRule($this->lowering))->modifier($form->node(3))],
            'opt_group_clause: GROUP_SYM BY ROLLUP_SYM ( group_list )' => [4, GroupingModifier::Rollup],
            'opt_group_clause: GROUP_SYM BY CUBE_SYM ( group_list )' => [4, GroupingModifier::Cube],
            default => throw ImplementationGap::production($form),
        };
        $items = $this->items($form->node($position));
        if (array_filter($items, static fn ($item): bool => $item instanceof DeferredOrderItem) !== []) {
            return new DeferredGrouping($items, $modifier);
        }
        $ordinary = [];
        foreach ($items as $item) {
            \SqlSemantics\Diagnostic\Check::invariant($item instanceof OrderItem, 'An ordinary grouping has scalar or ordinal items.');
            $ordinary[] = $item;
        }
        return new Grouping($ordinary, $modifier);
    }

    /**
     * @return list<OrderItem|DeferredOrderItem> Items in written order
     */
    public function items(Node $list): array
    {
        $nodes = (new Lists())->items($list);
        $items = [];
        for ($index = 0; $index < count($nodes); $index++) {
            $item = $nodes[$index];
            $direction = $item->name === 'order_ident' ? $nodes[++$index] : null;
            $items[] = $this->item($item, $direction);
        }
        return $items;
    }

    /**
     * Reads the same item role and direction as the ordinary clause rules.
     */
    public function item(Node $item, ?Node $direction = null): OrderItem|DeferredOrderItem
    {
        $form = $this->lowering->form($item);
        $rules = new ClauseRule($this->lowering);
        $ordering = match ($form->signature) {
            'order_ident: expr', 'grouping_expr: expr' => $direction === null ? null : $rules->direction($direction),
            'order_expr: expr opt_ordering_direction', 'grouping_expr: expr ordering_direction' => $rules->direction($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
        $operand = $this->lowering->expressions->operand($form->node(0));
        return $operand instanceof Scalar ? $rules->positions([new OrderItem($operand, $ordering)])[0] : new DeferredOrderItem($operand, $ordering);
    }
}
