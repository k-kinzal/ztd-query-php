<?php

declare(strict_types=1);

namespace Fuzz\Probe;

/**
 * Normalizes direct database interactions into a dialect-agnostic probe result.
 */
interface EngineProbe
{
    /**
     * Returns the SQL dialect handled by this probe.
     */
    public function dialect(): string;

    /**
     * Submits one generated statement to the engine and captures how far it got.
     */
    public function observe(string $sql): ProbeResult;
}
