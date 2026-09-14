<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use PDO;
use RuntimeException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * Builds a schema directly from information_schema columns.
 *
 * @visibility root
 */
final class CatalogSchema
{
    /**
     * Reads schema from information schema.
     * @throws RuntimeException
     */
    public function fetchSchemaFromInformationSchema(PDO $pdo, string $tableName): TableSchema
    {
        $schema = 'public';
        $table = $tableName;
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $table = $parts[1];
        }

        $rows = (new CatalogQuery())->columns($pdo, $schema, $table);

        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        $columns = [];
        foreach ($rows as $row) {
            $columnName = $row['column_name'];
            $type = (new CatalogColumn())->resolveType($row);
            $length = $row['character_maximum_length'] !== null ? (int) $row['character_maximum_length'] : null;
            $precision = $row['numeric_precision'] !== null ? (int) $row['numeric_precision'] : null;
            $scale = $row['numeric_scale'] !== null ? (int) $row['numeric_scale'] : null;
            $nullable = $row['is_nullable'] === 'YES';
            $default = (new CatalogColumn())->parseDefault($row['column_default']);
            $autoIncrement = $row['column_default'] !== null && str_contains($row['column_default'], 'nextval(');

            $columns[$columnName] = new ColumnDefinition(
                name: $columnName,
                type: $type,
                length: $length,
                precision: $precision,
                scale: $scale,
                nullable: $nullable,
                unsigned: false,
                default: $default,
                autoIncrement: $autoIncrement,
                generated: false,
                enumValues: null,
            );
        }

        return new TableSchema($table, $columns, []);
    }
}
