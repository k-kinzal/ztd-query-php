<?php

declare(strict_types=1);

namespace Deriver\Result;

/**
 * Shared derivations and the boundaries that prevent further expansion.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Explanation([], [], []))->nodes // => []
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
