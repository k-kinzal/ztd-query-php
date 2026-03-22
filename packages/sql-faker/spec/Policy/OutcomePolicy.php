<?php

declare(strict_types=1);

namespace Spec\Policy;

use Spec\Probe\ProbeResult;

/**
 * Separates dialect-specific witness interpretation from raw probe execution.
 */
interface OutcomePolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string;

    /**
     * Maps a normalized probe result to the outcome category used by spec assertions.
     */
    public function classify(ProbeResult $probeResult): OutcomeKind;
}
