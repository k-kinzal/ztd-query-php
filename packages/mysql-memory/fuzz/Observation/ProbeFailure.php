<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use RuntimeException;

/**
 * A failed required state read, retaining its driver evidence without parsing an error message.
 */
final class ProbeFailure extends RuntimeException
{
    /**
     * Preserves the original failure and binary-safe observations in ordinary fuzz reports.
     */
    public function __construct(public readonly Record $record, RuntimeException $previous)
    {
        parent::__construct('Cannot read observed state: ' . $record->sql . '; base64 serialized probe: ' . base64_encode(serialize($record)), 0, $previous);
    }

    /**
     * A missing database is meaningful only after the initial inventory and its conditions completed.
     */
    public function absentDatabase(): bool
    {
        $error = $this->record->raw['error'] ?? null;
        return $this->record->phase === 'state validation' && is_array($error) && ($error[0] ?? null) === 1049;
    }
}
