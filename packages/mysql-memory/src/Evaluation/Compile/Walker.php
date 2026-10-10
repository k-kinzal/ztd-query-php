<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Walks the nodes of a statement structure in the order of their properties, which is the order they are written in.
 *
 * @visibility MySqlMemory
 */
final class Walker
{
    /**
     * Finds the nodes of a class under a node, the node included, in written order.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param bool $intoQueries Whether to descend into subqueries
     * @return list<T>
     */
    public function find(object $root, string $class, bool $intoQueries = true): array
    {
        $found = [];
        if ($root instanceof Node) {
            $this->visit($root, $class, $intoQueries, $found, true);
        }

        return $found;
    }

    /**
     * Collects the matching nodes under a node, the node included.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param list<T> $found
     * @param-out list<T> $found
     */
    public function visit(Node $node, string $class, bool $intoQueries, array &$found, bool $root = false): void
    {
        if ($node instanceof $class) {
            $found[] = $node;
        }
        if (!$root && !$intoQueries && $node instanceof Query) {
            return;
        }
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            $this->visit($child, $class, $intoQueries, $found);
        }
    }
}
