<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

/**
 * A locking read requests a strength and a contention policy without acquiring locks.
 * @visibility public
 */
abstract class RowLock
{
    /**
     * Retains the execution policy, without evaluating any input rows.
     */
    public function __construct(public readonly LockStrength $strength, public readonly LockWait $wait = LockWait::Wait)
    {
    }
}
