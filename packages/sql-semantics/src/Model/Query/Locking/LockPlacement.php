<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the row-locking clauses of one query block against its dialect and its own input relations.
 * @visibility SqlSemantics
 */
final class LockPlacement
{
    /**
     * Requires row locks, a dialect that has them, dialect-specific strengths only in PostgreSQL, and named targets
     * that belong to the query block's input.
     *
     * @param list<RowLock> $locks
     * @param list<TableUse> $relations Relation occurrences of the query block's input
     * @throws InvalidStructure
     */
    public static function validate(array $locks, Origin $origin, array $relations): void
    {
        Collections::objects($locks, RowLock::class);
        if ($locks !== [] && $origin->dialect === Dialect::Sqlite) {
            throw new InvalidStructure('SQLite queries do not have locking clauses.');
        }
        if ($origin->dialect === Dialect::MySql && self::repeated($locks, $relations)) {
            throw new InvalidStructure('A MySQL query block locks each of its tables in at most one locking clause.');
        }
        foreach ($locks as $lock) {
            if ($origin->dialect === Dialect::MySql && in_array($lock->strength, [LockStrength::KeyShare, LockStrength::NoKeyUpdate], true)) {
                throw new InvalidStructure('This lock strength is specific to PostgreSQL.');
            }
            if ($lock instanceof NamedRowLock) {
                foreach ($lock->relations as $relation) {
                    if ($relation instanceof TableUse && !in_array($relation, $relations, true)) {
                        throw new InvalidStructure('A named lock target must belong to this query input.');
                    }
                }
            }
        }
    }

    /**
     * Reports whether some table would be locked by more than one clause, which MySQL rejects (error 3569): a named
     * target repeated in the OF lists, or a clause without OF next to another clause when the block reads a stored
     * table. PostgreSQL merges such clauses instead, keeping the strongest lock.
     *
     * @param list<RowLock> $locks
     * @param list<TableUse> $relations Relation occurrences of the query block's input
     */
    public static function repeated(array $locks, array $relations): bool
    {
        $named = [];
        $whole = 0;
        foreach ($locks as $lock) {
            if (!$lock instanceof NamedRowLock) {
                $whole++;
                continue;
            }
            foreach ($lock->relations as $relation) {
                if ($relation instanceof TableUse && in_array($relation, $named, true)) {
                    return true;
                }
                $named[] = $relation;
            }
        }
        $stored = array_filter($relations, static fn (TableUse $relation): bool => $relation instanceof \SqlSemantics\Model\Relation\NamedTableReference);
        return $whole > 0 && count($locks) > 1 && $stored !== [];
    }
}
