<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

/**
 * A locking read requests a strength and a contention policy without acquiring locks.
 * @visibility public
 * @example Reading a locking read's policy
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t FOR SHARE NOWAIT');
 *     [$statement->locks[0]->strength, $statement->locks[0]->wait] // => [\SqlSemantics\Model\Query\Locking\LockStrength::Share, \SqlSemantics\Model\Query\Locking\LockWait::NoWait]
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
