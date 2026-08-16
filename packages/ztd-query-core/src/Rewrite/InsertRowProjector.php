<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Projects an INSERT row to the table's complete column shape.
 */
final class InsertRowProjector
{
    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param list<string> $values
     * @param array<string, string> $defaults
     * @param array<string, string> $generatedValues
     * @return array<string, string>
     */
    public function project(
        array $tableColumns,
        array $insertColumns,
        array $values,
        array $defaults,
        array $generatedValues = [],
    ): array {
        if (count($insertColumns) !== count($values)) {
            throw new \InvalidArgumentException('Insert values count does not match column count.');
        }

        $provided = [];
        foreach ($insertColumns as $index => $column) {
            $provided[$column] = trim($values[$index]);
        }

        $columns = $tableColumns !== [] ? $tableColumns : $insertColumns;
        $projected = [];
        foreach ($columns as $column) {
            $expression = $provided[$column] ?? null;
            if ($expression === null || strcasecmp($expression, 'DEFAULT') === 0) {
                $expression = $generatedValues[$column] ?? $defaults[$column] ?? 'NULL';
            }
            $projected[$column] = $expression;
        }

        return $projected;
    }
}
