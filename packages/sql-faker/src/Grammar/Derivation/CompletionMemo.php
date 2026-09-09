<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

/**
 * Reuses exact minima, affordable witnesses and exhausted-budget proofs within one immutable plan.
 */
final class CompletionMemo
{
    /**
     * @var GenerationPlan<bool>|null
     */
    private ?GenerationPlan $plan = null;
    /**
     * @var array<string, int>
     */
    private array $minimums = [];
    /**
     * @var array<string, int>
     */
    private array $witnesses = [];
    /**
     * @var array<string, int>
     */
    private array $failures = [];

    /**
     * Never treats a nonminimal witness as proof that a smaller budget is impossible.
     * @param GenerationPlan<bool> $plan
     */
    public function recall(GenerationPlan $plan, string $key, int $budget, bool $minimum): ?int
    {
        $this->activate($plan);
        if (isset($this->minimums[$key])) {
            return $this->minimums[$key] <= $budget ? $this->minimums[$key] : PHP_INT_MAX;
        }
        if (!$minimum && isset($this->witnesses[$key]) && $this->witnesses[$key] <= $budget) {
            return $this->witnesses[$key];
        }
        return ($this->failures[$key] ?? -1) >= $budget ? PHP_INT_MAX : null;
    }

    /**
     * A finite result is a constructive witness; exhaustion proves only the searched budget range.
     * @param GenerationPlan<bool> $plan
     */
    public function remember(GenerationPlan $plan, string $key, int $budget, int $result, bool $minimum): void
    {
        $this->activate($plan);
        if ($result === PHP_INT_MAX) {
            $this->failures[$key] = max($this->failures[$key] ?? -1, $budget);
        } else {
            $this->witnesses[$key] = min($this->witnesses[$key] ?? PHP_INT_MAX, $result);
            if ($minimum) {
                $this->minimums[$key] = $result;
            }
        }
    }
    /**
     * Prevents queries or witness writes for another immutable plan from sharing state.
     * @param GenerationPlan<bool> $plan
     */
    public function activate(GenerationPlan $plan): void
    {
        if ($this->plan !== $plan) {
            $this->plan = $plan;
            $this->minimums = $this->witnesses = $this->failures = [];
        }
    }
}
