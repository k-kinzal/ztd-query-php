<?php

declare(strict_types=1);

namespace Spec\Probe;

/**
 * Normalizes live-engine witness execution into a dialect-agnostic probe result.
 */
interface EngineProbe
{
    /**
     * Returns the SQL dialect handled by this probe.
     */
    public function dialect(): string;

    /**
     * Submits one witness SQL string to the engine and captures how it responded.
     */
    public function observe(string $sql): ProbeResult;
}
