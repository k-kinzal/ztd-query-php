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
        $this->visit($root, $class, $intoQueries, $found, true);

        return $found;
    }

    /**
     * Collects matching nodes under a value.
     *
     * @param list<object> $found
     */
    public function visit(mixed $value, string $class, bool $intoQueries, array &$found, bool $root = false): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->visit($item, $class, $intoQueries, $found);
            }

            return;
        }
        if (!$value instanceof Node) {
            return;
        }
        if ($value instanceof $class) {
            $found[] = $value;
        }
        if (!$root && !$intoQueries && $value instanceof Query) {
            return;
        }
        foreach (get_object_vars($value) as $property) {
            $this->visit($property, $class, $intoQueries, $found);
        }
    }
}
