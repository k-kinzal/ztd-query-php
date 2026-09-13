<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;

/**
 * Checks classify deterministic invariants.
 */
final class ClassifyDeterministicChecker implements InvariantChecker
{
    private SqliteQueryGuard $guard;

    /**
     * Binds the dependencies used by this operation.
     */
    public function __construct(SqliteQueryGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Returns check.
     */
    public function check(string $sql): ?InvariantViolation
    {
        $result1 = $this->guard->classify($sql);
        $result2 = $this->guard->classify($sql);

        if ($result1 !== $result2) {
            return new InvariantViolation(
                'INV-L1-02',
                'classify() returned different results for the same SQL',
                $sql,
                [
                    'result1' => $result1 !== null ? $result1->value : 'null',
                    'result2' => $result2 !== null ? $result2->value : 'null',
                ]
            );
        }

        return null;
    }
}
