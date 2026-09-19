<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

/**
 * The work one analysis is allowed to do before it gives up and widens.
 *
 * Following calls and re-walking loop bodies is what makes the analyzer
 * precise, and is also what makes it possible for a pathological input to run
 * forever. The budget bounds both, so an exhausted budget degrades the result
 * into gaps rather than hanging the run.
 *
 * @visibility root
 */
final class EvaluationBudget
{
    private int $steps;

    /**
     * @param int $maxSteps How many expressions may be evaluated while walking one body
     * @param int $maxDepth How many nested calls may be followed
     * @param int $maxLoopPasses How many times a loop body is re-walked
     */
    public function __construct(
        public readonly int $maxSteps = 20000,
        public readonly int $maxDepth = 4,
        public readonly int $maxLoopPasses = 2,
    ) {
        $this->steps = 0;
    }

    /**
     * Spends one step, reporting whether the budget still allows work.
     */
    public function spend(): bool
    {
        $this->steps++;

        return $this->steps <= $this->maxSteps;
    }

    /**
     * Whether the step budget is used up.
     */
    public function isExhausted(): bool
    {
        return $this->steps > $this->maxSteps;
    }

    /**
     * How many steps have been spent.
     */
    public function spent(): int
    {
        return $this->steps;
    }

    /**
     * Refills the budget, at the beginning of a new body.
     */
    public function reset(): void
    {
        $this->steps = 0;
    }
}
