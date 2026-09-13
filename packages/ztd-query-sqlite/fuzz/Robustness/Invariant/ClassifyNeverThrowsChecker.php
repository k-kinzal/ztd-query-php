<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;

/**
 * Exercises classification and lets unexpected failures reach the fuzz process boundary.
 */
final class ClassifyNeverThrowsChecker implements InvariantChecker
{
    private SqliteQueryGuard $guard;

    /**
     * Binds the collaborator used by this invariant check.
     */
    public function __construct(SqliteQueryGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Invokes classification without intercepting unexpected engine or programmer errors.
     */
    public function check(string $sql): ?InvariantViolation
    {
        $classification = $this->guard->classify($sql);

        return null;
    }
}
