<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Locking;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableUse;

/**
 * Serializes lock modes, explicit target relations, and contention behavior.
 * @visibility SqlSemantics
 */
final class RowLocks
{
    /**
     * @param list<Locking\RowLock> $locks
     */
    public static function write(array $locks, Dialect $dialect): Tree
    {
        return new Tree('locking', array_map(static fn (Locking\RowLock $lock): Tree => self::clause($lock, $dialect), $locks));
    }

    /**
     * Uses the shared-lock spelling understood by legacy MySQL releases when possible.
     */
    public static function clause(Locking\RowLock $lock, Dialect $dialect): Tree
    {
        if ($dialect === Dialect::MySql && $lock instanceof Locking\AllRowLock && $lock->strength === Locking\LockStrength::Share && $lock->wait === Locking\LockWait::Wait) {
            return Build::keyword('LOCK IN SHARE MODE');
        }
        $parts = [Build::keyword('FOR ' . $lock->strength->value)];
        if ($lock instanceof Locking\NamedRowLock) {
            $parts[] = Build::keyword('OF');
            $parts[] = Build::separated(array_map(static fn (\SqlSemantics\Model\TableUse|Locking\UnresolvedLockRelation $target): Tree => Build::identifier($target instanceof TableUse ? [$target->alias ?? $target->declaration->name] : $target->name->parts, $dialect), $lock->relations));
        }
        if ($lock->wait !== Locking\LockWait::Wait) {
            $parts[] = Build::keyword($lock->wait->value);
        }
        return new Tree('lock', $parts);
    }
}
