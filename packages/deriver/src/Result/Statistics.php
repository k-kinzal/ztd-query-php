<?php

declare(strict_types=1);

namespace Deriver\Result;

/**
 * Work counts describe derivation; candidate dependency reuse reduces actual expansions.
 * A cached complete result retains the statistics of its original derivation. Timing is observational.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Statistics())->transfers // => 0
 */
final class Statistics
{
    /**
     * @param array<string, int> $expandedBodies Demanded source bodies by declaration
     * @param array<string, int> $expandedReferences Demanded references by owner and name
     * @param int $transfers transfers
     * @param int $graphs graphs
     * @param int $cacheHits cacheHits
     * @param float $seconds seconds
     * @param int $peakMemoryBytes peakMemoryBytes
     */
    public function __construct(
        public readonly int $transfers = 0,
        public readonly int $graphs = 0,
        public readonly int $cacheHits = 0,
        public readonly float $seconds = 0.0,
        public readonly int $peakMemoryBytes = 0,
        public readonly int $referenceExpansions = 0,
        public readonly int $bodyExpansions = 0,
        public readonly int $modelApplications = 0,
        public readonly int $sharedNodeHits = 0,
        public readonly int $constructedNodes = 0,
        public readonly int $retainedNodes = 0,
        public readonly array $expandedBodies = [],
        public readonly array $expandedReferences = [],
    ) {
    }
}
