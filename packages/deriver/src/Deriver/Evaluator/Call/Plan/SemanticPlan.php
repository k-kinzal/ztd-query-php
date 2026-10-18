<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan;

/**
 * Acyclic API semantics lowered to the source evaluator control-flow representation.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan([]))->actions // => []
 */
final class SemanticPlan
{
    /**
     * @param list<Action> $actions actions
     * @param list<string> $reads reads
     * @param list<string> $writes writes
     */
    public function __construct(
        public readonly array $actions,
        public readonly array $reads = [],
        public readonly array $writes = [],
    ) {
    }
}
