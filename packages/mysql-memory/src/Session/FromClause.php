<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Locates the names of the FROM clause of a query block where the server resolves them: its derived tables before the rest of the block, its ON conditions after WHERE.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility MySqlMemory
 */
final class FromClause
{
    /**
     * @param Locator $locator The locator that records the names
     */
    public function __construct(public readonly Locator $locator)
    {
    }

    /**
     * Locates the derived tables of a FROM clause, resolved before the rest of their block.
     *
     * @param list<int> $order
     */
    public function derived(?Node $relation, array $order): void
    {
        if ($relation instanceof DerivedTable) {
            $this->locator->visit($relation->query, 'field list', [...$order, 0, count($this->locator->places)]);

            return;
        }
        if ($relation instanceof JoinedTable) {
            $this->derived($relation->left, $order);
            $this->derived($relation->right, $order);

            return;
        }
        if ($relation instanceof Node && !$relation instanceof Query) {
            foreach (get_object_vars($relation) as $property) {
                if (is_array($property)) {
                    foreach ($property as $member) {
                        if ($member instanceof Node) {
                            $this->derived($member, $order);
                        }
                    }
                } elseif ($property instanceof Node && !$property instanceof ColumnUse) {
                    $this->derived($property, $order);
                }
            }
        }
    }

    /**
     * Locates the ON conditions of a FROM clause.
     *
     * @param list<int> $order
     */
    public function conditions(?Node $relation, array $order): void
    {
        if (!$relation instanceof Node || $relation instanceof DerivedTable) {
            return;
        }
        if ($relation instanceof JoinedTable) {
            $this->conditions($relation->left, [...$order, 0]);
            $this->conditions($relation->right, [...$order, 1]);
            $this->locator->visit($relation->on, 'on clause', [...$order, 2]);

            return;
        }
        foreach (get_object_vars($relation) as $property) {
            if (is_array($property)) {
                foreach (array_values($property) as $position => $member) {
                    if ($member instanceof Node) {
                        $this->conditions($member, [...$order, $position]);
                    }
                }
            }
        }
    }
}
