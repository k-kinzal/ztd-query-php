<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Represents the rewrite outcome for multiple SQL statements.
 */
final class MultiRewritePlan
{
    /**
     * Array of rewrite plans for each statement.
     *
     * @var RewritePlan[]
     */
    private array $plans;

    /**
     * @param RewritePlan[] $plans Array of rewrite plans.
     */
    public function __construct(array $plans)
    {
        $this->plans = $plans;
    }

    /**
     * Get all rewrite plans.
     *
     * @return RewritePlan[]
     */
    public function plans(): array
    {
        return $this->plans;
    }

    /**
     * Get the number of statements.
     */
    public function count(): int
    {
        return count($this->plans);
    }

    /**
     * Check if all statements are allowed (not FORBIDDEN).
     */
    public function allAllowed(): bool
    {
        foreach ($this->plans as $plan) {
            if ($plan->kind() === QueryKind::FORBIDDEN) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the first plan.
     */
    public function first(): ?RewritePlan
    {
        return $this->plans[0] ?? null;
    }

    /**
     * Get plan at specific index.
     */
    public function get(int $index): ?RewritePlan
    {
        return $this->plans[$index] ?? null;
    }
}
