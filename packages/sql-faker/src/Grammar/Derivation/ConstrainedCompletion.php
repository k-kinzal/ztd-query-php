<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;

/**
 * Finds the cheapest completion respecting occurrence-specific patterns and pending siblings.
 * Unconstrained grammar costs are an admissible lower bound, never a claimed constrained minimum.
 */
final class ConstrainedCompletion
{
    private readonly CompletionReduction $reduction;
    private readonly CompletionMemo $memo;
    private readonly \SqlFaker\Grammar\Choice\CompletionWitness $witness;
    private readonly \SqlFaker\Grammar\Choice\PatternProductions $productions;
    /**
     * Reuses the grammar's unconstrained fixed point for pruning completed constraint prefixes.
     */
    public function __construct(Grammar $grammar, private readonly CompletionCosts $costs)
    {
        $this->memo = new CompletionMemo();
        $this->productions = new \SqlFaker\Grammar\Choice\PatternProductions($grammar);
        $this->witness = new \SqlFaker\Grammar\Choice\CompletionWitness($costs, $this->productions, $this->memo);
        $this->reduction = new CompletionReduction($costs, new ConstraintDependencies($grammar));
    }

    /**
     * Finds the exact constrained minimum before a plan compiler draws its expansion budget.
     * @param list<Symbol> $symbols
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     */
    public function minimum(array $symbols, GenerationPlan $plan, array $occurrences, bool $nonEmpty, int $budget): int
    {
        return $this->complete($symbols, $plan, $occurrences, $nonEmpty, $budget, true);
    }

    /**
     * Stops at the first affordable witness when derivation only needs feasibility, not another shortest proof.
     * @param list<Symbol> $symbols
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     */
    public function within(array $symbols, GenerationPlan $plan, array $occurrences, bool $nonEmpty, int $budget): bool
    {
        return $this->complete($symbols, $plan, $occurrences, $nonEmpty, $budget, false) <= $budget;
    }

    /**
     * Reuses completion proofs for equivalent pending forms and pattern counters.
     * @param list<Symbol> $symbols
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     */
    public function complete(array $symbols, GenerationPlan $plan, array $occurrences, bool $nonEmpty, int $budget, bool $minimum): int
    {
        $nonEmpty = $nonEmpty && !$this->costs->hasTerminalOutput($symbols);
        $symbols = array_values(array_filter($symbols, static fn (Symbol $symbol): bool => $symbol instanceof NonTerminal));
        $key = (new CompletionState($symbols, $plan->patternState($occurrences), $nonEmpty, 0))->key();
        $cached = $this->memo->recall($plan, $key, $budget, $minimum);
        if ($cached !== null) {
            return $cached;
        }
        $result = $minimum ? null : $this->witness->complete($symbols, $plan, $occurrences, $nonEmpty, $budget);
        $result ??= $this->search($symbols, $plan, $occurrences, $nonEmpty, $budget, $minimum);
        $this->memo->remember($plan, $key, $budget, $result, $minimum);
        return $result;
    }

    /**
     * Searches finite frontiers within the caller's expansion budget, without generating or retrying SQL.
     * @param list<Symbol> $symbols
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     */
    public function search(array $symbols, GenerationPlan $plan, array $occurrences, bool $nonEmpty, int $budget, bool $minimum): int
    {
        if (!$plan->hasRemainingPatterns($occurrences)) {
            return $this->costs->completion(new Production($symbols), [], $nonEmpty);
        }
        $frontier = new CompletionFrontier($this->costs, $budget, $minimum);
        $frontier->offer($symbols, $plan->patternState($occurrences), $nonEmpty, 0);
        while (($state = $frontier->take()) !== null) {
            if ($state->symbols === []) {
                return $this->witness($state, $plan, $state->spent, $budget);
            }
            if (!$plan->hasRemainingPatterns($state->occurrences)) {
                $completion = $this->costs->completion(new Production($state->symbols), [], $state->nonEmpty);
                return $this->witness($state, $plan, CompletionCosts::add($state->spent, $completion), $budget);
            }
            $known = $this->memo->recall($plan, $state->key(), $budget - $state->spent, $minimum);
            if ($known !== null) {
                if ($known !== PHP_INT_MAX) {
                    $frontier->offer([], $state->occurrences, false, $state->spent + $known, $state);
                }
                continue;
            }
            if ($this->reduction->reduce($state, $plan, $frontier)) {
                continue;
            }
            $name = $state->symbols[0]->value();
            $occurrence = $state->occurrences[$name] ?? 0;
            $pattern = $plan->patternAt($name, $occurrence);
            $nextOccurrences = $plan->patternState([...$state->occurrences, $name => $occurrence + 1]);
            foreach ($this->productions->matching($name, $pattern) as $production) {
                $frontier->offer([...$production->symbols, ...array_slice($state->symbols, 1)], $nextOccurrences, $state->nonEmpty, $state->spent + 1, $state);
            }
        }
        return PHP_INT_MAX;
    }

    /**
     * Shares each suffix of the actual successful path, not unrelated visited branches or a sampled impossibility claim.
     * @param GenerationPlan<bool> $plan
     */
    public function witness(CompletionState $state, GenerationPlan $plan, int $total, int $budget): int
    {
        do {
            $this->memo->remember($plan, $state->key(), $budget - $state->spent, $total - $state->spent, false);
            $state = $state->parent;
        } while ($state !== null);
        return $total;
    }
}
