<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Trace;

use Deriver\Deriver\Evaluator\Evidence\Frontier;

/**
 * Shared derivations and the boundaries that prevent further expansion.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Execution\Trace\Explanation([], [], []))->nodes // => []
 */
final class Explanation
{
    /**
     * @param array<string, Derivation> $nodes nodes
     * @param list<Frontier> $frontiers frontiers
     * @param list<string> $assumptions assumptions
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $frontiers,
        public readonly array $assumptions,
    ) {
    }
}
