<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

/**
 * The mode of a row lock: shared, as FOR SHARE takes it, or exclusive, as FOR UPDATE and the writes take it.
 *
 * Shared locks of different transactions are compatible; an exclusive lock is compatible with no
 * lock of another transaction. A transaction that holds an exclusive lock holds the shared lock too.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking.html#innodb-shared-exclusive-locks.
 *
 * @visibility MySqlMemory
 */
enum LockMode
{
    case Shared;
    case Exclusive;

    /**
     * Tells whether a lock of this mode, held by one transaction, lets another take a lock of a mode.
     */
    public function admits(self $wanted): bool
    {
        return match ($this) {
            self::Shared => $wanted === self::Shared,
            self::Exclusive => false,
        };
    }

    /**
     * Tells whether a lock of this mode includes a lock of a mode, for the transaction that holds it.
     */
    public function covers(self $wanted): bool
    {
        return match ($this) {
            self::Shared => $wanted === self::Shared,
            self::Exclusive => true,
        };
    }
}
