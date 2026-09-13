<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;

/**
 * Classify never throws checker for PostgreSQL queries.
 */
final class ClassifyNeverThrowsChecker implements InvariantChecker
{
    private PgSqlQueryGuard $guard;
    /**
     * Initializes the collaborators and state used by this classify never throws checker.
     */
    public function __construct(PgSqlQueryGuard $guard)
    {
        $this->guard = $guard;
    }
    /**
     * Check.
     */
    public function check(string $sql): ?InvariantViolation
    {
        $this->guard->classify($sql);
        return null;
    }
}
