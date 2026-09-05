<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use Closure;

/**
 * Answers the least a rule can cost to finish.
 *
 * A walk that has to stop soon needs to know which alternative gets there
 * first, and that depends on every rule below it. The answer is found by
 * assuming nothing terminates and then relaxing: each pass takes the cheapest
 * alternative of each rule under the current answers, and the passes repeat
 * until none of them changes anything. A rule that never settles below the sentinel
 * can only expand into itself and so can never be finished at all.
 *
 * The same relaxation answers two different questions depending on what a
 * step is counted as: tokens written, or rules expanded. What each costs is
 * given to the constructor rather than written twice.
 *
 * @visibility root
 */
final class TerminationCost
{
    /** @var array<string, int> Rule name => least it can cost to finish */
    private array $minimum;

    /**
     * @param Grammar $grammar Grammar whose rules are being costed
     * @param Closure(string): bool $terminalSupported Answers whether a terminal can be written at all
     * @param int $perTerminal What one written terminal costs
     * @param int $perExpansion What expanding one rule costs
     */
    public function __construct(
        Grammar $grammar,
        private readonly Closure $terminalSupported,
        private readonly int $perTerminal,
        private readonly int $perExpansion,
    ) {
        $this->minimum = $this->settled($grammar);
    }

    /**
     * Answers the least one symbol can cost to finish.
     *
     * A symbol the grammar declares no rule for is a token, and costs what
     * reaching a token costs — unless nothing can write it, in which case it
     * cannot be finished at all.
     *
     * @param string $symbol Symbol to cost
     *
     * @return int Least it can cost, or PHP_INT_MAX when it can never be finished
     */
    public function of(string $symbol): int
    {
        return $this->minimum[$symbol] ?? (($this->terminalSupported)($symbol) ? 1 : PHP_INT_MAX);
    }

    /**
     * Answers the least one production can cost to finish.
     *
     * @param Production $production Production to cost
     *
     * @return int Least it can cost, or PHP_INT_MAX when any part of it can never be finished
     */
    public function ofProduction(Production $production): int
    {
        return $this->sum($production->symbols, $this->minimum);
    }

    /**
     * Relaxes every rule's cost until no pass changes one.
     *
     * @param Grammar $grammar Grammar whose rules are being costed
     *
     * @return array<string, int> Rule name => least it can cost to finish
     */
    public function settled(Grammar $grammar): array
    {
        $minimum = array_fill_keys(array_keys($grammar->ruleMap), PHP_INT_MAX);

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->ruleMap as $name => $rule) {
                $best = $minimum[$name];
                foreach ($rule->alternatives as $alternative) {
                    $cost = $this->sum($alternative->symbols, $minimum);
                    if ($cost === PHP_INT_MAX) {
                        continue;
                    }
                    $cost += $this->perExpansion;
                    if ($cost < $best) {
                        $best = $cost;
                    }
                }
                if ($best !== $minimum[$name]) {
                    $minimum[$name] = $best;
                    $changed = true;
                }
            }
        }

        return $minimum;
    }

    /**
     * Adds up what a sequence of symbols costs under the answers found so far.
     *
     * @param list<Symbol> $symbols Symbols to cost
     * @param array<string, int> $minimum Answers found so far
     *
     * @return int Least the sequence can cost, or PHP_INT_MAX when any part of it can never be finished
     */
    public function sum(array $symbols, array $minimum): int
    {
        $total = 0;
        foreach ($symbols as $symbol) {
            if ($symbol instanceof Terminal) {
                if (!($this->terminalSupported)($symbol->value)) {
                    return PHP_INT_MAX;
                }
                $total += $this->perTerminal;
                continue;
            }
            if (!$symbol instanceof NonTerminal) {
                continue;
            }

            $cost = $minimum[$symbol->value] ?? (($this->terminalSupported)($symbol->value) ? 1 : PHP_INT_MAX);
            if ($cost === PHP_INT_MAX || $total > PHP_INT_MAX - $cost) {
                return PHP_INT_MAX;
            }
            $total += $cost;
        }

        return $total;
    }
}
