<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Grammar;

/**
 * What the closure of an item set adds, precomputed per nonterminal.
 *
 * Closing an item set adds every rule of every nonterminal reachable from a
 * nonterminal after a dot. Those additions depend only on the nonterminal,
 * so they are computed once: the items that begin with each symbol, and the
 * empty rules, which complete immediately.
 *
 * @visibility root
 */
final class ClosureIndex
{
    /**
     * Items encode a rule and a dot position as rule times this width plus dot.
     */
    public readonly int $itemWidth;

    /**
     * @var array<int, array<int, list<int>>>
     */
    private readonly array $startsWith;

    /**
     * @var array<int, list<int>>
     */
    private readonly array $emptyRules;

    /**
     * Precomputes the closure additions of every nonterminal.
     *
     * @param Grammar $grammar Grammar to index
     */
    public function __construct(Grammar $grammar)
    {
        $width = 1;
        foreach ($grammar->rules as $rule) {
            $width = max($width, $rule->length() + 1);
        }
        $this->itemWidth = $width;
        $startsWith = [];
        $emptyRules = [];
        $symbols = $grammar->symbols;
        for ($nonterminal = $symbols->terminalCount(); $nonterminal < $symbols->count(); $nonterminal++) {
            $bySymbol = [];
            $empty = [];
            foreach ($this->reachable($grammar, $nonterminal) as $reached) {
                foreach ($grammar->rulesOf($reached) as $index) {
                    $first = $grammar->rules[$index]->rhs[0] ?? null;
                    if ($first === null) {
                        $empty[] = $index;
                    } else {
                        $bySymbol[$first][] = $index * $width + 1;
                    }
                }
            }
            $startsWith[$nonterminal] = $bySymbol;
            $emptyRules[$nonterminal] = $empty;
        }
        $this->startsWith = $startsWith;
        $this->emptyRules = $emptyRules;
    }

    /**
     * Answers the nonterminals whose rules the closure of a nonterminal adds.
     *
     * @param Grammar $grammar Grammar to walk
     * @param int $nonterminal Nonterminal after the dot
     *
     * @return list<int> The nonterminal itself and every one reachable through a leading nonterminal
     */
    public function reachable(Grammar $grammar, int $nonterminal): array
    {
        $seen = [$nonterminal => true];
        $pending = [$nonterminal];
        while ($pending !== []) {
            $current = array_pop($pending);
            foreach ($grammar->rulesOf($current) as $index) {
                $first = $grammar->rules[$index]->rhs[0] ?? null;
                if ($first !== null && !$grammar->symbols->isTerminal($first) && !isset($seen[$first])) {
                    $seen[$first] = true;
                    $pending[] = $first;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * Answers the closure items that begin with each symbol, dot advanced past it.
     *
     * @param int $nonterminal Nonterminal after the dot
     *
     * @return array<int, list<int>> Items by their first symbol
     */
    public function startsWith(int $nonterminal): array
    {
        return $this->startsWith[$nonterminal] ?? [];
    }

    /**
     * Answers the empty rules the closure of a nonterminal completes at once.
     *
     * @param int $nonterminal Nonterminal after the dot
     *
     * @return list<int> Rule indexes
     */
    public function emptyRules(int $nonterminal): array
    {
        return $this->emptyRules[$nonterminal] ?? [];
    }
}
