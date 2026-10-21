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
     * Visits operands and resolved common-table dependencies, without rebinding names.
     *
     * Definitions are reached through their semantic reference identity. Unused
     * WITH bodies remain inert, and recursive bindings are visited once.
     *
     * @param bool $childrenFirst Whether an occurrence follows its completed operands
     * @return list<Node>
     */
    public function resolved(Node $root, \SqlSemantics\Statement\Fact\Facts $facts, bool $childrenFirst = false): array
    {
        /** @var list<array{Node, bool}> $pending */
        $pending = [[$root, false]];
        $seen = [];
        $found = [];
        while ($pending !== []) {
            [$node, $completed] = array_pop($pending);
            if ($completed) {
                $found[] = $node;
                continue;
            }
            $id = spl_object_id($node);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($childrenFirst) {
                $pending[] = [$node, true];
            } else {
                $found[] = $node;
            }
            $properties = get_object_vars($node);
            unset($properties['with']);
            $children = [];
            array_walk_recursive($properties, static function ($value) use (&$children): void {
                if ($value instanceof Node) {
                    $children[] = $value;
                }
            });
            if ($node instanceof \SqlSemantics\Statement\NamedRelation && $facts->covers($node)) {
                $resolution = $facts->relation($node)->table;
                if ($resolution instanceof \SqlSemantics\Statement\Reference\Table\CommonTable) {
                    $children[] = $resolution->definition;
                }
            }
            foreach (array_reverse($children) as $child) {
                $pending[] = [$child, false];
            }
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
            $this->visit($child, $class, $intoQueries, $found, $root && $node instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot);
        }
    }
}
