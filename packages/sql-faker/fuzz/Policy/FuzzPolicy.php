<?php

declare(strict_types=1);

namespace Fuzz\Policy;

use Fuzz\Probe\ProbeResult;

/**
 * Separates dialect-specific acceptance rules from raw engine probing.
 */
interface FuzzPolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string;

    /**
     * Maps a normalized probe result to either an ignore or crash decision.
     */
    public function classify(ProbeResult $probeResult): FuzzDecision;
}
