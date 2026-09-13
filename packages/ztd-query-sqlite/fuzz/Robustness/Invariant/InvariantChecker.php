<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Describes a SQL invariant checked by the robustness targets.
 */
interface InvariantChecker
{
    /**
     * Checks the SQL contract and returns a violation when an observable invariant fails.
     */
    public function check(string $sql): ?InvariantViolation;
}
