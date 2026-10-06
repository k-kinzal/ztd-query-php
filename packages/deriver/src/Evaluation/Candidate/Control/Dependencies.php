<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Control;

use Deriver\Evaluation\Candidate\Context;
use Deriver\Evaluation\Candidate\Graph;

/**
 * Indexes selecting edges using immediate postdominators, without path enumeration.
 * @visibility root
 */
final class Dependencies
{
    /**
     * Associates each block with branches that select it.
     * @return array<int, list<array{int, int}>>|null Selecting edges, or an interrupted index
     */
    public function build(Graph $graph, ?Context $context = null): ?array
    {
        $post = $this->postdominators($graph, $context);
        if ($post === null) {
            return null;
        }
        $dependencies = [];
        foreach ($graph->body->blocks as $id => $block) {
            if ($block->terminator->kind !== 'branch') {
                continue;
            }
            foreach ($block->terminator->targets as $target) {
                $runner = $target;
                while (isset($post[$runner]) && $runner !== ($post[$id] ?? -1) && $runner !== -1) {
                    if ($context?->work() !== null) {
                        return null;
                    }
                    $dependencies[$runner][] = [$id, $target];
                    $runner = $post[$runner];
                }
            }
        }
        return $dependencies;
    }

    /**
     * Finds immediate dominators of the reversed graph in reverse postorder.
     * @return array<int, int>|null Immediate postdominator map, or interruption
     */
    public function postdominators(Graph $graph, ?Context $context): ?array
    {
        $order = $this->order($graph);
        $rank = array_flip($order);
        $post = [-1 => -1];
        do {
            $changed = false;
            foreach ($order as $id) {
                if ($id === -1) {
                    continue;
                }
                if ($context?->work() !== null) {
                    return null;
                }
                $successors = $graph->successors[$id] ?? [];
                $candidate = null;
                foreach ($successors === [] ? [-1] : $successors as $successor) {
                    if (isset($post[$successor])) {
                        $candidate = $candidate === null ? $successor : $this->intersect($candidate, $successor, $post, $rank);
                    }
                }
                if ($candidate !== null && ($post[$id] ?? null) !== $candidate) {
                    $post[$id] = $candidate;
                    $changed = true;
                }
            }
        } while ($changed);
        return $post;
    }

    /**
     * Computes reverse postorder without recursive host calls or quadratic node sets.
     * @return list<int> Reverse-graph traversal rooted at the virtual exit
     */
    public function order(Graph $graph): array
    {
        $reverse = [];
        foreach ($graph->body->blocks as $id => $_) {
            $successors = $graph->successors[$id] ?? [];
            foreach ($successors === [] ? [-1] : $successors as $successor) {
                $reverse[$successor][] = $id;
            }
        }
        $seen = [];
        $order = [];
        /** @var list<array{int, bool}> $pending */
        $pending = [[-1, false]];
        while ($pending !== []) {
            [$id, $ready] = array_pop($pending);
            if ($ready) {
                $order[] = $id;
            } elseif (!isset($seen[$id])) {
                $seen[$id] = true;
                $pending[] = [$id, true];
                foreach ($reverse[$id] ?? [] as $parent) {
                    $pending[] = [$parent, false];
                }
            }
        }
        return array_reverse($order);
    }

    /**
     * Finds the nearest shared postdominator by climbing the two parent chains.
     * @param array<int, int> $post Established parent links
     * @param array<int, int> $rank Reverse postorder ranks
     */
    public function intersect(int $left, int $right, array $post, array $rank): int
    {
        while ($left !== $right) {
            if ($rank[$left] > $rank[$right]) {
                $left = $post[$left];
            } else {
                $right = $post[$right];
            }
        }
        return $left;
    }
}
