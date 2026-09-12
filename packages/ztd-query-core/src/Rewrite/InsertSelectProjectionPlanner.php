<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Works out what each column of a rewritten INSERT ... SELECT reads back as.
 */
final class InsertSelectProjectionPlanner
{
    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, string> $defaults
     * @param array<string, int> $generatedIdentityStarts
     * @return list<InsertSelectProjection>
     */
    public function plan(
        array $tableColumns,
        array $insertColumns,
        array $defaults,
        array $generatedIdentityStarts = [],
    ): array {
        $sourceIndexes = [];
        foreach ($insertColumns as $index => $column) {
            $sourceIndexes[$column] = $index;
        }

        $columns = $tableColumns !== [] ? $tableColumns : $insertColumns;
        $projections = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $sourceIndexes)) {
                $projections[] = InsertSelectProjection::source($column, $sourceIndexes[$column]);
                continue;
            }
            if (array_key_exists($column, $generatedIdentityStarts)) {
                $projections[] = InsertSelectProjection::generatedIdentity($column, $generatedIdentityStarts[$column]);
                continue;
            }
            if (array_key_exists($column, $defaults)) {
                $projections[] = InsertSelectProjection::defaultExpression($column, $defaults[$column]);
                continue;
            }
            $projections[] = InsertSelectProjection::nullValue($column);
        }

        return $projections;
    }
}
