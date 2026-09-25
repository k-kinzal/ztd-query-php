<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\TableUse;
use WeakMap;

/**
 * Finds the row locks that MySQL refuses in the input query of a table being created.
 *
 * MySQL fails CREATE TABLE ... SELECT when any query block of the input, including subqueries, derived tables,
 * common table expressions and set-operation operands, takes an exclusive (FOR UPDATE) lock on a stored table or
 * view (error 1746); a shared lock, or an exclusive lock over derived rows only, is accepted.
 * @visibility SqlSemantics
 */
final class TableCreationLocks
{
    /**
     * Reports whether a query block of the query locks a stored table or view FOR UPDATE.
     */
    public static function exclusive(BoundQuery $query): bool
    {
        $seen = new WeakMap();
        $pending = [$query];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value) && self::operand($value) && !isset($seen[$value])) {
                $seen[$value] = true;
                if (($value instanceof \SqlSemantics\Model\BoundSelect || $value instanceof \SqlSemantics\Model\Statement\TableStatement) && self::locksStored($value->locks, $value->relations)) {
                    return true;
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return false;
    }

    /**
     * Reports whether an exclusive lock of one query block covers one of its stored tables or views.
     *
     * @param list<RowLock> $locks
     * @param list<TableUse> $relations Relation occurrences of the query block's input
     */
    public static function locksStored(array $locks, array $relations): bool
    {
        foreach ($locks as $lock) {
            if ($lock->strength !== LockStrength::Update) {
                continue;
            }
            $covered = $lock instanceof NamedRowLock ? $lock->relations : $relations;
            if (array_filter($covered, static fn (TableUse|UnresolvedLockRelation $relation): bool => $relation instanceof NamedTableReference) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Keeps the search inside semantic operands, away from provenance, parser trees and schema declarations.
     */
    public static function operand(object $value): bool
    {
        $class = $value::class;
        return str_starts_with($class, 'SqlSemantics\\Model\\')
            && !$value instanceof \SqlSemantics\Model\Statement\Origin
            && !$value instanceof \SqlSemantics\Model\Sql\Tree
            && !str_starts_with($class, 'SqlSemantics\\Model\\Transformation\\');
    }
}
