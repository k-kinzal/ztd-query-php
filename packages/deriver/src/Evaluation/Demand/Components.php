<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Demand;

/**
 * Finds strongly connected demand components in deterministic dependency order.
 * @visibility root
 */
final class Components
{
    /**
     * Groups cycles using iterative Kosaraju traversal without using the PHP call stack.
     * @param Table $table Dynamic dependency graph
     * @return list<list<string>> Components with dependencies before their dependents
     */
    public function find(Table $table): array
    {
        $visited = [];
        $components = [];
        foreach (array_reverse($this->order($table)) as $id) {
            if (isset($visited[$id])) {
                continue;
            }
            $component = [];
            $pending = [$id];
            while ($pending !== []) {
                $current = array_pop($pending);
                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;
                $component[] = $current;
                $neighbors = array_keys($table->cells[$current]->dependents);
                sort($neighbors);
                array_push($pending, ...array_reverse($neighbors));
            }
            sort($component);
            $components[] = $component;
        }
        return array_reverse($components);
    }

    /**
     * Computes finishing order while visiting each dependency edge at most once.
     * @param Table $table Dynamic dependency graph
     * @return list<string> Postorder demand identifiers
     */
    public function order(Table $table): array
    {
        $ids = array_keys($table->cells);
        sort($ids);
        $visited = [];
        $order = [];
        foreach ($ids as $id) {
            /** @var list<array{string, bool}> $pending */
            $pending = [[$id, false]];
            while ($pending !== []) {
                [$current, $expanded] = array_pop($pending);
                if ($expanded) {
                    $order[] = $current;
                } elseif (!isset($visited[$current])) {
                    $visited[$current] = true;
                    $pending[] = [$current, true];
                    $neighbors = array_keys($table->cells[$current]->dependencies);
                    sort($neighbors);
                    foreach (array_reverse($neighbors) as $neighbor) {
                        $pending[] = [$neighbor, false];
                    }
                }
            }
        }
        return $order;
    }
}
