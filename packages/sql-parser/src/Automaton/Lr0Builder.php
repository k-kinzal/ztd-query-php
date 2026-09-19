<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Grammar;

/**
 * Builds the LR(0) automaton of a grammar from kernel items alone.
 *
 * A state is identified by its kernel. Its closure is never materialised:
 * the transitions out of it are computed from the kernel items and the
 * precomputed closure index, which keeps grammars with thousands of rules
 * tractable.
 *
 * @visibility root
 */
final class Lr0Builder
{
    /**
     * Builds the automaton.
     *
     * @param Grammar $grammar Grammar to build for
     *
     * @return Lr0Automaton The states in the order they were discovered, the start state first
     */
    public function build(Grammar $grammar): Lr0Automaton
    {
        $index = new ClosureIndex($grammar);
        $width = $index->itemWidth;
        $kernels = [[0]];
        $ids = ['0' => 0];
        $transitions = [];
        $reductions = [];
        for ($state = 0; isset($kernels[$state]); $state++) {
            $targets = [];
            $completed = [];
            foreach ($kernels[$state] as $item) {
                $rule = $grammar->rules[intdiv($item, $width)];
                $symbol = $rule->rhs[$item % $width] ?? null;
                if ($symbol === null) {
                    $completed[$rule->index] = true;
                    continue;
                }
                $targets[$symbol][$item + 1] = true;
                if ($grammar->symbols->isTerminal($symbol)) {
                    continue;
                }
                foreach ($index->startsWith($symbol) as $first => $items) {
                    foreach ($items as $closureItem) {
                        $targets[$first][$closureItem] = true;
                    }
                }
                foreach ($index->emptyRules($symbol) as $empty) {
                    $completed[$empty] = true;
                }
            }
            $row = [];
            foreach ($targets as $symbol => $items) {
                $kernel = array_keys($items);
                sort($kernel);
                $key = implode(',', $kernel);
                if (!isset($ids[$key])) {
                    $ids[$key] = count($kernels);
                    $kernels[] = $kernel;
                }
                $row[$symbol] = $ids[$key];
            }
            $transitions[] = $row;
            $rules = array_keys($completed);
            sort($rules);
            $reductions[] = $rules;
        }

        return new Lr0Automaton($kernels, $transitions, $reductions);
    }
}
