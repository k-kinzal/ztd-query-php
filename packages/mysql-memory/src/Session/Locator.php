<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Finds where in a statement each column name is read, and in which order the server resolves it.
 *
 * The server resolves a query block in this order: the derived tables of its FROM clause, its
 * select list, WHERE, the ON conditions, GROUP BY, HAVING and ORDER BY; it names the clause it
 * resolves in the message of a name it cannot resolve. A subquery is resolved in the place it is
 * written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility MySqlMemory
 */
final class Locator
{
    /**
     * @var array<int, array{string, list<int>}> The clause and resolution order of each column name, by object id
     */
    public array $places = [];

    /**
     * @var list<ColumnUse> Keeps the located nodes alive
     */
    private array $nodes = [];

    /**
     * Locates the column names of a statement.
     */
    public function statement(Node $statement): self
    {
        if ($statement instanceof Update) {
            foreach ($statement->assignments as $index => $assignment) {
                $this->visit($assignment, 'field list', [1, $index]);
            }
            $this->visit($statement->where, 'where clause', [2]);
            $this->visit($statement->orderBy, 'order clause', [6]);

            return $this;
        }
        if ($statement instanceof Delete) {
            $this->visit($statement->where, 'where clause', [2]);
            $this->visit($statement->orderBy, 'order clause', [6]);

            return $this;
        }
        $this->visit($statement, 'field list', []);

        return $this;
    }

    /**
     * Tells whether one resolution order comes before another: element by element, a prefix first.
     *
     * @param list<int> $left
     * @param list<int> $right
     */
    public static function precedes(array $left, array $right): bool
    {
        foreach ($left as $index => $value) {
            if (!isset($right[$index])) {
                return false;
            }
            if ($value !== $right[$index]) {
                return $value < $right[$index];
            }
        }

        return count($left) < count($right);
    }

    /**
     * Answers the located column names.
     *
     * @return list<ColumnUse>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Answers the clause and order of a column name, or null when it was not located.
     *
     * @return array{string, list<int>}|null
     */
    public function place(Node $node): ?array
    {
        return $this->places[spl_object_id($node)] ?? null;
    }

    /**
     * Locates the column names under a value, read in a clause at an order.
     *
     * The nodes under the value are visited depth first in the order of their properties; each
     * property, and each member of a list, adds its position to the order.
     *
     * @param Node|list<Node>|null $value
     * @param list<int> $order
     */
    public function visit(Node|array|null $value, string $clause, array $order): void
    {
        $values = [$value];
        $orders = [$order];
        while (($at = array_pop($orders)) !== null) {
            $current = array_pop($values);
            $children = [];
            $positions = [];
            if (is_array($current)) {
                foreach (array_values($current) as $index => $item) {
                    $children[] = $item;
                    $positions[] = [...$at, $index];
                }
            } elseif ($current instanceof Select) {
                $this->select($current, $at);
            } elseif ($current instanceof Node) {
                if ($current instanceof ColumnUse) {
                    $this->places[spl_object_id($current)] = [$clause, $at];
                    $this->nodes[] = $current;
                }
                $index = 0;
                foreach (get_object_vars($current) as $property) {
                    $children[] = $property;
                    $positions[] = [...$at, $index++];
                }
            }
            array_push($values, ...array_reverse($children));
            array_push($orders, ...array_reverse($positions));
        }
    }

    /**
     * Locates the column names of a query block in the order the server resolves its clauses.
     *
     * @param list<int> $order
     */
    public function select(Select $select, array $order): void
    {
        $this->from($select->from, $order);
        foreach ($select->items as $index => $item) {
            $this->visit($item instanceof SelectExpression ? $item->expression : $item, 'field list', [...$order, 1, $index]);
        }
        $this->visit($select->where, 'where clause', [...$order, 2]);
        $this->conditions($select->from, [...$order, 3]);
        $this->visit($select->groupBy, 'group statement', [...$order, 4]);
        $this->visit($select->having, 'having clause', [...$order, 5]);
        $this->visit($select->orderBy, 'order clause', [...$order, 6]);
        $this->visit($select->limit, 'field list', [...$order, 7]);
    }

    /**
     * Locates the derived tables of a FROM clause, resolved before the rest of their block.
     *
     * @param list<int> $order
     */
    public function from(?Node $relation, array $order): void
    {
        if ($relation instanceof DerivedTable) {
            $this->visit($relation->query, 'field list', [...$order, 0, count($this->places)]);

            return;
        }
        if ($relation instanceof JoinedTable) {
            $this->from($relation->left, $order);
            $this->from($relation->right, $order);

            return;
        }
        if ($relation instanceof Node && !$relation instanceof Query) {
            foreach (get_object_vars($relation) as $property) {
                if (is_array($property)) {
                    foreach ($property as $member) {
                        if ($member instanceof Node) {
                            $this->from($member, $order);
                        }
                    }
                } elseif ($property instanceof Node && !$property instanceof ColumnUse) {
                    $this->from($property, $order);
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
            $this->visit($relation->on, 'on clause', [...$order, 2]);

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
