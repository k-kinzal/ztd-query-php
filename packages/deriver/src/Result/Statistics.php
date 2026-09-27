<?php

declare(strict_types=1);

namespace Deriver\Result;

/**
 * Logical work counts are independent of cache warmth; timing is observational.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Statistics())->transfers // => 0
 */
final class Statistics
{
    /**
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
    ) {
    }
}
