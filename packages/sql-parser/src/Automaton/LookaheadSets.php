<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Grammar;

/**
 * The LALR(1) lookahead of every reduction, by the method of DeRemer and Pennello.
 *
 * Each nonterminal transition of the automaton is a node. Its direct reads
 * are the terminals that can follow it at once; the reads relation adds what
 * follows a nullable nonterminal after it; the includes relation adds what
 * follows a rule that ends with it. The lookahead of a completed rule is the
 * union over the transitions the rule was entered from.
 *
 * @visibility root
 */
final class LookaheadSets
{
    /**
     * @var array<int, array<int, array<int, int>>>
     */
    private readonly array $lookaheads;

    /**
     * Computes the lookahead sets.
     *
     * @param Grammar $grammar Grammar the automaton was built from
     * @param Lr0Automaton $automaton Its LR(0) automaton
     * @param NullableSet $nullable Its nullable nonterminals
     * @param Digraph $digraph Closes sets over the relations
     */
    public function __construct(Grammar $grammar, Lr0Automaton $automaton, NullableSet $nullable, Digraph $digraph = new Digraph())
    {
        $symbols = $grammar->symbols;
        $nodes = [];
        $from = [];
        $symbol = [];
        foreach ($automaton->transitions as $state => $row) {
            foreach (array_keys($row) as $moved) {
                if (!$symbols->isTerminal($moved)) {
                    $nodes[$state][$moved] = count($from);
                    $from[] = $state;
                    $symbol[] = $moved;
                }
            }
        }
        [$sets, $reads] = $this->reads($automaton, $nodes, $symbols->terminalCount(), $nullable);
        $sets = $digraph->close(count($from), $reads, $sets);
        $includes = [];
        $lookback = [];
        foreach ($from as $node => $state) {
            foreach ($grammar->rulesOf($symbol[$node]) as $index) {
                $rhs = $grammar->rules[$index]->rhs;
                $current = $state;
                foreach ($rhs as $position => $moved) {
                    if (!$symbols->isTerminal($moved) && $nullable->tailNullable($rhs, $position + 1)) {
                        $includes[$nodes[$current][$moved]][] = $node;
                    }
                    $current = $automaton->transitions[$current][$moved];
                }
                $lookback[$current][$index][] = $node;
            }
        }
        $sets = $digraph->close(count($from), $includes, $sets);
        $lookaheads = [];
        foreach ($automaton->reductions as $state => $rules) {
            $lookaheads[$state] = [];
            foreach ($rules as $index) {
                $set = Bitset::empty($symbols->terminalCount());
                foreach ($lookback[$state][$index] ?? [] as $node) {
                    Bitset::union($set, $sets[$node]);
                }
                $lookaheads[$state][$index] = $set;
            }
        }
        $this->lookaheads = $lookaheads;
    }

    /**
     * Computes the direct reads of every nonterminal transition and the reads relation between them.
     *
     * @param Lr0Automaton $automaton The automaton
     * @param array<int, array<int, int>> $nodes Transition number by state and nonterminal
     * @param int $terminalCount How many terminals a read set may hold
     * @param NullableSet $nullable Nonterminals that derive the empty string
     *
     * @return array{array<int, array<int, int>>, array<int, list<int>>} Direct reads of each transition, and the transitions each one reads through a nullable nonterminal
     */
    public function reads(Lr0Automaton $automaton, array $nodes, int $terminalCount, NullableSet $nullable): array
    {
        $sets = [];
        $reads = [];
        foreach ($nodes as $state => $row) {
            foreach ($row as $moved => $node) {
                $target = $automaton->transitions[$state][$moved];
                $set = Bitset::empty($terminalCount);
                foreach (array_keys($automaton->transitions[$target]) as $next) {
                    if ($next < $terminalCount) {
                        Bitset::add($set, $next);
                    } elseif ($nullable->isNullable($next)) {
                        $reads[$node][] = $nodes[$target][$next];
                    }
                }
                $sets[$node] = $set;
            }
        }
        ksort($sets);

        return [$sets, $reads];
    }

    /**
     * Answers the lookahead of every rule completed in a state.
     *
     * @param int $state State number
     *
     * @return array<int, array<int, int>> Bitset of terminals by rule index
     */
    public function of(int $state): array
    {
        return $this->lookaheads[$state] ?? [];
    }
}
