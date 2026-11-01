<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Statement\Node;

/**
 * The ordered item domain shared by SELECT and DO.
 *
 * An item is an expression or a qualified wildcard. A bare wildcard is
 * permitted only as the first item. At least one item is required.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/do.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectItems
{
    /**
     * Checks element roles and their ordering without deriving the projection.
     *
     * @param list<Node> $items The supplied items
     * @param string $emptyMessage The message when no item is supplied
     * @return non-empty-list<SelectExpression|Star|TableWildcard>
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an item or its position is invalid
     */
    public function checked(array $items, string $emptyMessage): array
    {
        $list = [];
        foreach (Check::listOf($items, Node::class, $emptyMessage, 1) as $position => $item) {
            Check::input($item instanceof SelectExpression || $item instanceof TableWildcard || ($item instanceof Star && $position === 0), 'A select list item is an expression or a qualified star, and an unqualified star comes first.');
            $list[] = $item;
        }

        return $list;
    }
}
