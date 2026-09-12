<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

/**
 * Projects referenced column values between related rows.
 *
 */
final class RelationProjection
{
    /**
     * The columns of a table that some relation reads off it.
     *
     * @return list<string>
     */
    public function referencedColumns(FixturePlan $plan, string $table): array
    {
        $columns = [];

        foreach ($plan->dependentsOf($table) as $relation) {
            foreach ($relation->parent()->columns as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<mixed> $overrides
     */
    public function isAlreadyLinked(Relation $relation, array $overrides): bool
    {
        foreach (array_keys($relation->columnMap()) as $childColumn) {
            if (!array_key_exists($childColumn, $overrides)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $parentRow
     * @return array<string, mixed>
     * @throws PlanSchemaException
     */
    public function project(array $parentRow, Relation $relation): array
    {
        $values = [];

        foreach ($relation->columnMap() as $childColumn => $parentColumn) {
            if (!array_key_exists($parentColumn, $parentRow)) {
                throw PlanSchemaException::missingValue($childColumn, $relation->parent(), $parentColumn);
            }

            $values[$childColumn] = $parentRow[$parentColumn];
        }

        return $values;
    }
}
