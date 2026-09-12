<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Implements the Invariant Checker contract for MySQL.
 */
interface InvariantChecker
{
    /**
     * Check for the supplied MySQL input.
     */
    public function check(string $sql): ?InvariantViolation;
}
