<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * The contract for invariant checker.
 */
interface InvariantChecker
{
    /**
     * Check.
     *
     * @param string $sql
     * @return ?InvariantViolation
     */
    public function check(string $sql): ?InvariantViolation;
}
