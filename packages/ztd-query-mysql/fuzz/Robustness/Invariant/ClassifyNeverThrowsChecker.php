<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;

/**
 * The classify never throws checker, as invariant checker.
 */
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
     * Checks that reading a statement never fails, however it was written.
     *
     * Nothing is caught here on purpose: classifying is meant to answer what
     * a statement is or answer nothing, never to fail. Anything that escapes
     * is recorded by the fuzzer as the crash it is.
     *
     * @param string $sql Statement the fuzzer drew
     *
     * @return InvariantViolation|null Always nothing, because failing is not returning
     */
    public function check(string $sql): ?InvariantViolation
    {
        $this->guard->classify($sql);

        return null;
    }
}
