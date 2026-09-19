<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

/**
 * The actions of one state once its conflicts have been settled.
 *
 * @visibility root
 */
final class ResolvedState
{
    /**
     * @param array<int, int> $actions Action code by terminal, explicit errors included
     * @param array<int, int> $reductionCounts How many terminals each completed rule still reduces on
     * @param int $shiftReduceConflicts Terminals where a shift won by default
     * @param int $reduceReduceConflicts Terminals where an earlier rule won by default
     */
    public function __construct(
        public readonly array $actions,
        public readonly array $reductionCounts,
        public readonly int $shiftReduceConflicts,
        public readonly int $reduceReduceConflicts,
    ) {
    }
}
