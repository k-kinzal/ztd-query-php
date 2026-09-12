<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation;

use SqlFaker\Generation\Plan\GenerationPlan;

/**
 * Eliminates independent subtrees using exact empty/non-empty costs instead of enumerating their derivations.
 */
final class CompletionReduction
{
    /**
     * Independent subtrees cannot consume any remaining pattern counter and therefore commute with the constrained walk.
     */
    public function __construct(private readonly CompletionCosts $costs, private readonly ConstraintDependencies $dependencies)
    {
    }

    /**
     * Preserves both output possibilities whenever the pending plan still requires a non-empty result.
     * @param GenerationPlan<bool> $plan
     */
    public function reduce(CompletionState $state, GenerationPlan $plan, CompletionFrontier $frontier): bool
    {
        $affected = $this->dependencies->affected($plan, $state->occurrences);
        $independent = [];
        $dependent = [];
        foreach ($state->symbols as $symbol) {
            if (isset($affected[$symbol->value()])) {
                $dependent[] = $symbol;
            } else {
                $independent[] = $symbol;
            }
        }
        if ($independent === []) {
            return false;
        }
        $costs = $this->costs->sequence($independent);
        if (!$state->nonEmpty) {
            $frontier->offer($dependent, $state->occurrences, false, CompletionCosts::add($state->spent, min($costs)), $state);
        } else {
            foreach ($costs as $emits => $cost) {
                if ($cost !== PHP_INT_MAX) {
                    $frontier->offer($dependent, $state->occurrences, $emits === 0, CompletionCosts::add($state->spent, $cost), $state);
                }
            }
        }
        return true;
    }
}
