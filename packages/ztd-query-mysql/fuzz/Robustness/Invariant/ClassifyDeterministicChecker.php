<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\MySql\Dialect\MySqlQueryGuard;

/**
 * The classify deterministic checker, as invariant checker.
 */
final class ClassifyDeterministicChecker implements InvariantChecker
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
     * Checks that reading the same statement twice says the same thing.
     *
     * @param string $sql Statement the fuzzer drew
     *
     * @return InvariantViolation|null What was violated, or null where nothing was
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
