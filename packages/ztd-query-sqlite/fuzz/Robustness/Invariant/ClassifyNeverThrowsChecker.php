<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use Throwable;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;

/**
 * The classify never throws checker, as invariant checker.
 */
final class ClassifyNeverThrowsChecker implements InvariantChecker
{
    private SqliteQueryGuard $guard;

    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteQueryGuard $guard
     */
    public function __construct(SqliteQueryGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Check.
     *
     * @param string $sql
     * @return ?InvariantViolation
     */
    public function check(string $sql): ?InvariantViolation
    {
        try {
            $classification = $this->guard->classify($sql);
            return null;
        } catch (Throwable $e) {
            return new InvariantViolation(
                'INV-L1-01',
                'classify() threw an exception',
                $sql,
                [
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]
            );
        }
    }
}
