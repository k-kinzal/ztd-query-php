<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the filtering, grouping, window and ordering clauses of every grammar generation.
 *
 * Rule: MYSQL-QUERY-CLAUSES-001. Scope: where_clause, opt_where_clause,
 * having_clause, opt_having_clause, opt_qualify_clause, group_clause,
 * opt_group_clause, group_list, grouping_expr, olap_opt, opt_order_clause,
 * order_clause, order_list, order_expr, order_ident, order_dir,
 * opt_ordering_direction, ordering_direction, opt_window_clause,
 * window_definition_list, window_definition. A clause that writes nothing
 * is absent. Ordering and grouping items keep their written order and
 * direction; in a query ORDER BY or GROUP BY an unsigned integer item is a
 * select list position (MYSQL-ORDINAL-001), elsewhere (windows,
 * GROUP_CONCAT) it is the expression it is. Constructs: Grouping,
 * GroupingModifier, OrderItem, OutputOrdinal, WindowDefinition. Terminates:
 * lists are flattened iteratively. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class ClauseRule
{
    /**
     * The productions of a predicate clause by the position of its expression; null for an absent clause.
     */
    private const PREDICATES = [
        'opt_where_clause:' => null, 'where_clause:' => null, 'opt_where_clause: WHERE expr' => 1, 'where_clause: WHERE expr' => 1,
        'opt_having_clause:' => null, 'having_clause:' => null, 'opt_having_clause: HAVING expr' => 1, 'having_clause: HAVING expr' => 1,
        'opt_qualify_clause:' => null, 'opt_qualify_clause: QUALIFY_SYM expr' => 1,
    ];

    /**
     * The productions of a sort direction.
     */
    private const DIRECTIONS = [
        'order_dir:' => null, 'opt_ordering_direction:' => null, 'order_dir: ASC' => Direction::Ascending, 'order_dir: DESC' => Direction::Descending,
        'ordering_direction: ASC' => Direction::Ascending, 'ordering_direction: DESC' => Direction::Descending,
    ];

    /**
     * The list productions of ordering and grouping items.
     */
    private const LISTS = [
        'order_list: order_list , order_ident order_dir' => true, 'order_list: order_ident order_dir' => true, 'order_list: order_list , order_expr' => true,
        'order_list: order_expr' => true, 'group_list: group_list , order_ident order_dir' => true, 'group_list: order_ident order_dir' => true,
        'group_list: group_list , grouping_expr' => true, 'group_list: grouping_expr' => true,
    ];

    /**
     * The super-aggregate modifiers written after the grouping list.
     */
    private const MODIFIERS = ['olap_opt:' => null, 'olap_opt: WITH_CUBE_SYM' => GroupingModifier::WithCube, 'olap_opt: WITH_ROLLUP_SYM' => GroupingModifier::WithRollup];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a WHERE, HAVING or QUALIFY clause; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function predicate(Node $clause): ?Scalar
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_where_clause: where_clause') {
            $form = $this->lowering->form($form->node(0));
        }
        if (!array_key_exists($form->signature, self::PREDICATES)) {
            throw ImplementationGap::production($form);
        }
        $position = self::PREDICATES[$form->signature];

        return $position === null ? null : $this->lowering->expressions->expression($form->node($position));
    }

    /**
     * Lowers a GROUP BY clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function grouping(Node $clause): ?Grouping
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_group_clause:', 'group_clause:' => null,
            'opt_group_clause: GROUP_SYM BY group_list olap_opt', 'group_clause: GROUP_SYM BY group_list olap_opt' => new Grouping($this->positions($this->items($form->node(2))), $this->modifier($form->node(3))),
            'opt_group_clause: GROUP_SYM BY ROLLUP_SYM ( group_list )' => new Grouping($this->positions($this->items($form->node(4))), GroupingModifier::Rollup),
            'opt_group_clause: GROUP_SYM BY CUBE_SYM ( group_list )' => new Grouping($this->positions($this->items($form->node(4))), GroupingModifier::Cube),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the modifier written after a grouping list.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function modifier(Node $modifier): ?GroupingModifier
    {
        $form = $this->lowering->form($modifier);
        if (!array_key_exists($form->signature, self::MODIFIERS)) {
            throw ImplementationGap::production($form);
        }

        return self::MODIFIERS[$form->signature];
    }

    /**
     * Lowers the ORDER BY clause of a query, with select list positions; an absent clause is empty.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function ordering(Node $clause): array
    {
        return $this->positions($this->orderItems($clause));
    }

    /**
     * Lowers an ORDER BY clause or an ordering or grouping list as written; an absent clause is empty.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function orderItems(Node $clause): array
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_order_clause:' => [],
            'opt_order_clause: order_clause' => $this->orderItems($form->node(0)),
            'order_clause: ORDER_SYM BY order_list' => $this->items($form->node(2)),
            default => $this->items($clause),
        };
    }

    /**
     * Lowers the items of an ordering or grouping list in written order.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $form = $this->lowering->form($list);
        if (!isset(self::LISTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $nodes = (new Lists())->items($list);
        $items = [];
        for ($index = 0; $index < count($nodes); $index++) {
            if ($nodes[$index]->name === 'order_ident') {
                $items[] = $this->item($nodes[$index], $nodes[$index + 1]);
                $index++;
                continue;
            }
            $items[] = $this->item($nodes[$index]);
        }

        return $items;
    }

    /**
     * Lowers one ordering or grouping item: an order_expr or grouping_expr, or an order_ident with its order_dir.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Node $item, ?Node $direction = null): OrderItem
    {
        $form = $this->lowering->form($item);

        return match ($form->signature) {
            'order_ident: expr', 'grouping_expr: expr' => new OrderItem($this->lowering->expressions->expression($form->node(0)), $direction === null ? null : $this->direction($direction)),
            'order_expr: expr opt_ordering_direction', 'grouping_expr: expr ordering_direction' => new OrderItem($this->lowering->expressions->expression($form->node(0)), $this->direction($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a sort direction; no direction is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function direction(Node $direction): ?Direction
    {
        $form = $this->lowering->form($direction);
        if ($form->signature === 'opt_ordering_direction: ordering_direction') {
            $form = $this->lowering->form($form->node(0));
        }
        if (!array_key_exists($form->signature, self::DIRECTIONS)) {
            throw ImplementationGap::production($form);
        }

        return self::DIRECTIONS[$form->signature];
    }

    /**
     * Answers the items with every unsigned integer read as a select list position.
     *
     * @param list<OrderItem> $items
     * @return list<OrderItem>
     */
    public function positions(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $expression = $item->expression;
            $result[] = $expression instanceof NumberLiteral && $expression->form === NumberForm::Integer ? new OrderItem(new OutputOrdinal($expression), $item->direction) : $item;
        }

        return $result;
    }

    /**
     * Lowers the WINDOW clause; an absent clause is empty.
     *
     * @return list<WindowDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function windows(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_window_clause:') {
            return [];
        }
        if ($form->signature !== 'opt_window_clause: WINDOW_SYM window_definition_list') {
            throw ImplementationGap::production($form);
        }
        $list = $this->lowering->form($form->node(1));
        if ($list->signature !== 'window_definition_list: window_definition' && $list->signature !== 'window_definition_list: window_definition_list , window_definition') {
            throw ImplementationGap::production($list);
        }
        $windows = [];
        foreach ((new Lists())->items($list->node) as $definition) {
            $window = $this->lowering->form($definition);
            if ($window->signature !== 'window_definition: window_name AS window_spec') {
                throw ImplementationGap::production($window);
            }
            $windows[] = new WindowDefinition($this->lowering->calls->windowName($window->node(0)), $this->lowering->calls->windowSpecification($window->node(2)));
        }

        return $windows;
    }
}
