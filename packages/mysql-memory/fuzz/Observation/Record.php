<?php

declare(strict_types=1);

namespace Fuzz\Observation;

/**
 * Retains one engine observation even when a later probe or restoration fails.
 *
 * Raw values are stored before comparison contracts. The observation phase and
 * cleanup phase are separate so cleanup cannot erase the location of a failure.
 * This record contains no connections or exceptions and can be serialized without
 * losing binary values. A restored fixture does not imply a complete observation.
 */
final class Record
{
    /**
     * The observation operation in progress, or complete after every probe succeeds.
     */
    public string $phase = 'initial restoration';

    /**
     * The independent cleanup operation in progress, retaining the failed observation phase.
     */
    public string $cleanup = 'pending';

    /**
     * Whether the input connection was released and the fixture restoration completed.
     */
    public bool $restored = false;

    /**
     * @var array<string, mixed> Values captured before the current phase
     */
    public array $raw = [];

    /**
     * Identifies the engine and exact input whose partial observations are retained.
     */
    public function __construct(public readonly string $engine, public readonly string $sql, public readonly string $mode)
    {
    }
}
