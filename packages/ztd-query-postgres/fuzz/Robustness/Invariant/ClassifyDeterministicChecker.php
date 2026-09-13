<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;

/**
 * Classify deterministic checker for PostgreSQL queries.
 */
final class ClassifyDeterministicChecker implements InvariantChecker
{
    private PgSqlQueryGuard $guard;
    /**
     * Initializes the collaborators and state used by this classify deterministic checker.
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
        $result1 = $this->guard->classify($sql);
        $result2 = $this->guard->classify($sql);
        if ($result1 !== $result2) {
            return new InvariantViolation('INV-L1-02', 'classify() returned different results for the same SQL', $sql, ['result1' => $result1 !== null ? $result1->value : 'null', 'result2' => $result2 !== null ? $result2->value : 'null']);
        }
        return null;
    }
}
