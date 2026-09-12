<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation\Completion;

use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionMemo;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;

/**
 * Proves cheap feasible continuations before the complete search; a failed probe proves nothing.
 * Every emitted witness actually walks the patterns, budget and output obligation without constructing SQL.
 */
final class CompletionWitness
{
    /** @var array<string, list<array{Production, array{int, int}}>> */
    private array $choices = [];

    /**
     * Shares immutable production matches and unconstrained costs with the complete solver.
     */
    public function __construct(private readonly CompletionCosts $costs, private readonly PatternProductions $productions, private readonly CompletionMemo $memo)
    {
    }

    /**
     * Limits this optional proof attempt to 256 expansions; null always delegates to the complete search.
     * @param list<Symbol> $symbols
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     */
    public function complete(array $symbols, GenerationPlan $plan, array $occurrences, bool $nonEmpty, int $budget): ?int
    {
        $path = [];
        for ($spent = 0; $spent <= min(256, $budget); ++$spent) {
            $nonEmpty = $nonEmpty && !$this->costs->hasTerminalOutput($symbols);
            $symbols = array_values(array_filter($symbols, static fn (Symbol $symbol): bool => $symbol instanceof NonTerminal));
            if ($symbols === []) {
                return $nonEmpty ? null : $this->remember($path, $plan, $spent, $budget);
            }
            $key = (new CompletionState($symbols, $plan->patternState($occurrences), $nonEmpty, $spent))->key();
            $path[] = [$key, $spent];
            $known = $this->memo->recall($plan, $key, $budget - $spent, false);
            if ($known !== null) {
                return $known === PHP_INT_MAX ? null : $this->remember($path, $plan, $spent + $known, $budget);
            }
            if ($spent === min(256, $budget)) {
                return null;
            }
            $name = $symbols[0]->value();
            $occurrence = $occurrences[$name] ?? 0;
            $tail = array_slice($symbols, 1);
            $tailCosts = $this->costs->sequence($tail);
            $best = null;
            $bestCost = PHP_INT_MAX;
            foreach ($this->choices($name, $plan->patternAt($name, $occurrence)) as [$production, $costs]) {
                $combined = CompletionCosts::combine($costs, $tailCosts);
                $cost = $nonEmpty ? $combined[1] : min($combined);
                if ($cost < $bestCost) {
                    $best = $production;
                    $bestCost = $cost;
                }
            }
            if ($best === null || $bestCost >= $budget - $spent) {
                return null;
            }
            $symbols = [...$best->symbols, ...$tail];
            $occurrences[$name] = $occurrence + 1;
        }
        return null;
    }

    /**
     * Stores only suffix costs from the actual successful walk, as affordable upper bounds.
     * @param list<array{string, int}> $path
     * @param GenerationPlan<bool> $plan
     */
    public function remember(array $path, GenerationPlan $plan, int $total, int $budget): int
    {
        foreach ($path as [$key, $spent]) {
            $this->memo->remember($plan, $key, $budget - $spent, $total - $spent, false);
        }
        return $total;
    }

    /**
     * Keeps the cheapest empty and emitting alternatives as proposals, never as constrained minimum proofs.
     * @return list<array{Production, array{int, int}}>
     */
    public function choices(string $name, ?ProductionPattern $pattern): array
    {
        $key = $name . ':' . serialize($pattern);
        if (!isset($this->choices[$key])) {
            $best = [];
            foreach ($this->productions->matching($name, $pattern) as $production) {
                $costs = $this->costs->sequence($production->symbols);
                foreach ([0, 1] as $state) {
                    if ($costs[$state] < ($best[$state][1][$state] ?? PHP_INT_MAX)) {
                        $best[$state] = [$production, $costs];
                    }
                }
            }
            $this->choices[$key] = array_values($best);
        }
        return $this->choices[$key];
    }
}
