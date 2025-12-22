<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite\Shadowing;

use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Schema\SchemaRegistry;

/**
 * Builds and merges CTEs that shadow target tables.
 */
final class CteShadowing
{
    /**
     * CTE generator for shadow tables.
     *
     * @var CteGenerator
     */
    private CteGenerator $cteGenerator;

    /**
     * Schema registry for column ordering.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * @param CteGenerator $cteGenerator Emits CTE fragments per table.
     * @param SchemaRegistry $schemaRegistry Provides column ordering.
     */
    public function __construct(
        CteGenerator $cteGenerator,
        SchemaRegistry $schemaRegistry
    ) {
        $this->cteGenerator = $cteGenerator;
        $this->schemaRegistry = $schemaRegistry;
    }

    /**
     * Apply shadowing CTEs to the SQL when tables are referenced.
     *
     * @param array<string, array<int, array<string, mixed>>> $tableData
     */
    public function apply(string $sql, array $tableData): string
    {
        $ctes = [];
        foreach ($tableData as $tableName => $rows) {
            if (stripos($sql, $tableName) === false) {
                continue;
            }

            $columns = $this->schemaRegistry->getColumns($tableName);
            if ($columns === null && $rows !== []) {
                $columns = array_keys($rows[0]);
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $column) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                    }
                }
            }

            // Skip tables with no rows and no columns - we can't shadow unknown tables
            if ($columns === null && $rows === []) {
                continue;
            }

            $columnTypes = $this->schemaRegistry->getColumnTypes($tableName);

            $ctes[] = $this->cteGenerator->generate($tableName, $rows, $columns, $columnTypes);
        }

        if ($ctes === []) {
            return $sql;
        }

        $cteString = implode(",\n", $ctes);
        $pattern = '/^(\s*(?:(?:\/\*.*?\*\/)|(?:--.*?\n)|(?:#.*?\n)|\s)*)WITH\b/is';
        if (preg_match($pattern, $sql) === 1) {
            return (string) preg_replace($pattern, '$1WITH ' . $cteString . ",\n", $sql, 1);
        }

        return "WITH $cteString\n$sql";
    }
}
