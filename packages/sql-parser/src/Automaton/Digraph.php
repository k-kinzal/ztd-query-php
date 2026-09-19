<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

/**
 * Closes sets over a relation: each node ends with the union of every node it reaches.
 *
 * This is the traversal of DeRemer and Pennello, which finds the strongly
 * connected components of the relation and gives every member of a component
 * the same set. It runs on an explicit stack, as the relations of a large
 * grammar chain deeper than the call stack should be asked to go.
 *
 * @visibility root
 */
final class Digraph
{
    /**
     * Closes the sets.
     *
     * @param int $nodeCount How many nodes there are, numbered from zero
     * @param array<int, list<int>> $edges Nodes each node reaches directly
     * @param array<int, array<int, int>> $sets Initial set of each node, as bitset words
     *
     * @return array<int, array<int, int>> Closed set of each node
     */
    public function close(int $nodeCount, array $edges, array $sets): array
    {
        $marks = array_fill(0, $nodeCount, 0);
        $stack = [];
        for ($root = 0; $root < $nodeCount; $root++) {
            if ($marks[$root] === 0) {
                $this->traverse($root, $edges, $sets, $marks, $stack);
            }
        }

        return $sets;
    }

    /**
     * Traverses the relation from one node, closing every node it reaches.
     *
     * @param int $root Node to start from
     * @param array<int, list<int>> $edges Nodes each node reaches directly
     * @param array<int, array<int, int>> $sets Sets being closed, updated in place
     * @param array<int, int> $marks Traversal marks, updated in place
     * @param list<int> $stack Nodes of the component being explored, updated in place
     */
    public function traverse(int $root, array $edges, array &$sets, array &$marks, array &$stack): void
    {
        $stack[] = $root;
        $marks[$root] = count($stack);
        $frames = [[$root, 0, count($stack)]];
        while ($frames !== []) {
            $top = count($frames) - 1;
            [$node, $edge, $depth] = $frames[$top];
            $next = $edges[$node][$edge] ?? null;
            if ($next !== null) {
                $frames[$top][1] = $edge + 1;
                if ($marks[$next] === 0) {
                    $stack[] = $next;
                    $marks[$next] = count($stack);
                    $frames[] = [$next, 0, count($stack)];
                } else {
                    $marks[$node] = min($marks[$node], $marks[$next]);
                    Bitset::union($sets[$node], $sets[$next]);
                }
                continue;
            }
            array_pop($frames);
            if ($marks[$node] === $depth) {
                while (($member = array_pop($stack)) !== null) {
                    $marks[$member] = PHP_INT_MAX;
                    $sets[$member] = $sets[$node];
                    if ($member === $node) {
                        break;
                    }
                }
            }
            if ($frames !== []) {
                $parent = $frames[count($frames) - 1][0];
                $marks[$parent] = min($marks[$parent], $marks[$node]);
                Bitset::union($sets[$parent], $sets[$node]);
            }
        }
    }
}
