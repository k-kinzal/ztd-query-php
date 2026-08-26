<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use Throwable;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;

final class ClassifyNeverThrowsChecker implements InvariantChecker
{
    private MySqlQueryGuard $guard;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlQueryGuard $guard
     */
    public function __construct(MySqlQueryGuard $guard)
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
            $this->guard->classify($sql);
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
