<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;

/**
 * Merges the keys of one PostgreSQL CREATE TABLE that the server builds as one index (transformIndexConstraints):
 * a unique key with the same key columns, included columns, NULLS [NOT] DISTINCT and deferrability as the primary key
 * or an earlier unique key is dropped, and its name passes to that key when it has none. ALTER TABLE merges nothing.
 *
 * @visibility SqlSemantics
 */
final class PostgreSqlKeyMerges
{
    /**
     * Returns the constraints with the redundant unique keys merged into the key the server keeps.
     *
     * @param list<TableConstraint> $constraints Constraints one CREATE TABLE declares, in SQL order
     * @return list<TableConstraint>
     */
    public static function merge(array $constraints): array
    {
        $kept = [];
        foreach ($constraints as $position => $constraint) {
            if ($constraint instanceof Constraint\PrimaryKey) {
                $kept[] = $position;
                break;
            }
        }
        $result = $constraints;
        foreach ($constraints as $position => $constraint) {
            if (!$constraint instanceof Constraint\UniqueKey) {
                continue;
            }
            $prior = self::prior($result, $kept, $constraint);
            if ($prior === null) {
                $kept[] = $position;
                continue;
            }
            if ($result[$prior]->name === null && $constraint->name !== null) {
                $result[$prior] = PostgreSqlConstraintNames::named($result[$prior], $constraint->name);
            }
            unset($result[$position]);
        }
        return array_values($result);
    }

    /**
     * Returns the position of the kept key that builds the same index as the given unique key, or null.
     *
     * @param array<int, TableConstraint> $constraints
     * @param list<int> $kept
     */
    public static function prior(array $constraints, array $kept, Constraint\UniqueKey $key): ?int
    {
        foreach ($kept as $position) {
            $candidate = $constraints[$position];
            if (($candidate instanceof Constraint\PrimaryKey || $candidate instanceof Constraint\UniqueKey) && self::signature($candidate) === self::signature($key)) {
                return $position;
            }
        }
        return null;
    }

    /**
     * Returns what makes two key indexes the same index: key columns, included columns, NULLS DISTINCT and deferrability.
     *
     * @return array{list<string>, list<string>, bool, string}
     */
    public static function signature(Constraint\PrimaryKey|Constraint\UniqueKey $key): array
    {
        return [array_map(MySqlCounterKeys::name(...), $key->keys), $key->index->include, $key->nullsDistinct, $key->checking->value];
    }
}
