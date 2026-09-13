<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Invariant checker for PostgreSQL queries.
 */
interface InvariantChecker
{
    /**
     * Check.
     */
    public function check(string $sql): ?InvariantViolation;
}
