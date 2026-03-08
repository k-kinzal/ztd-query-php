<?php

declare(strict_types=1);

namespace Fuzz\Probe;

/**
 * Immutable record of what the database did with one fuzz-generated statement.
 */
final class ProbeResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ProbePhase $phase,
        public readonly ?string $sqlState,
        public readonly ?int $errorCode,
        public readonly ?string $message,
    ) {
    }

    /**
     * Creates a probe result for a statement the engine accepted.
     */
    public static function accepted(ProbePhase $phase = ProbePhase::None): self
    {
        return new self(true, $phase, null, null, null);
    }

    /**
     * Creates a probe result for a statement the engine rejected.
     */
    public static function failed(
        ProbePhase $phase,
        ?string $sqlState,
        ?int $errorCode,
        ?string $message,
    ): self {
        return new self(false, $phase, $sqlState, $errorCode, $message);
    }
}
