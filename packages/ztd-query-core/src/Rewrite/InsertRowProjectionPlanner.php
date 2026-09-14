<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Works out what each column of a rewritten INSERT ... VALUES reads back as.
 */
final class InsertRowProjectionPlanner
{
    /**
     * @param list<string> $tableColumns
     * @param array<string, string> $providedExpressions
     * @param array<string, string> $defaults
     * @param array<string, int> $generatedIdentityValues
     * @return list<InsertRowProjection>
     */
    public function plan(
        array $tableColumns,
        array $providedExpressions,
        array $defaults,
        array $generatedIdentityValues = [],
    ): array {
        $columns = $tableColumns !== [] ? $tableColumns : array_keys($providedExpressions);
        $projections = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $providedExpressions)) {
                $projections[] = InsertRowProjection::provided($column, $providedExpressions[$column]);
                continue;
            }
            if (array_key_exists($column, $generatedIdentityValues)) {
                $projections[] = InsertRowProjection::generatedIdentity($column, $generatedIdentityValues[$column]);
                continue;
            }
            if (array_key_exists($column, $defaults)) {
                $projections[] = InsertRowProjection::defaultExpression($column, $defaults[$column]);
                continue;
            }
            $projections[] = InsertRowProjection::nullValue($column);
        }

        return $projections;
    }
}
