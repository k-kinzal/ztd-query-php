<?php

declare(strict_types=1);

namespace Fuzz\Policy;

/**
 * Immutable decision returned by a fuzz policy after classifying a probe result.
 *
 * The decision tells the fuzz target whether the observed database response is an
 * expected rejection that should be ignored or a bug candidate that should stop
 * the current run and persist the crashing input.
 */
final class FuzzDecision
{
    public function __construct(
        public readonly bool $shouldCrash,
        public readonly string $classification,
        public readonly string $reason,
    ) {
    }

    /**
     * Creates a decision that should fail the current fuzz run.
     */
    public static function crash(string $classification, string $reason): self
    {
        return new self(true, $classification, $reason);
    }

    /**
     * Creates a decision that allows fuzzing to continue.
     */
    public static function ignore(string $classification, string $reason): self
    {
        return new self(false, $classification, $reason);
    }
}
