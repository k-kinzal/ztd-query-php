<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Splits table definitions while tracking parentheses.
 *
 * @visibility root
 */
final class DefinitionList
{
    /**
     * @param list<string> $tablePrimaryKeys
     * @return array<string, ColumnDefinition>
     */
    public function parseColumns(string $columnsBlock, string $tableName, array $tablePrimaryKeys): array
    {
        $columns = [];
        $definitions = $this->splitColumnDefinitions($columnsBlock);

        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            if ($this->isTableConstraint($definition)) {
                continue;
            }

            $column = (new ColumnParser())->parseColumnDefinition($definition, $tablePrimaryKeys);
            if ($column !== null) {
                $columns[$column->name] = $column;
            }
        }

        return $columns;
    }

    /**
     * Separates column definitions while preserving nested and quoted SQL.
     *
     * @return list<string>
     */
    public function splitColumnDefinitions(string $columnsBlock): array
    {
        return (new \SqlFixture\Schema\DefinitionSegments())->split($columnsBlock);
    }

    /**
     * Recognizes table constraints that do not declare a column.
     */
    public function isTableConstraint(string $definition): bool
    {
        $definition = strtoupper(trim($definition));

        return preg_match('/^(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|CONSTRAINT)\b/i', $definition) === 1;
    }
}
