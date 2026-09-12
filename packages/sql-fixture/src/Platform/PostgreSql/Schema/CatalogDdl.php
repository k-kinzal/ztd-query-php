<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use PDO;

/**
 * Reconstructs CREATE TABLE SQL from PostgreSQL catalog columns.
 *
 * @visibility root
 */
final class CatalogDdl
{
    /**
     * Reconstructs create table.
     */
    public function reconstructCreateTable(PDO $pdo, string $tableName): ?string
    {
        $schema = 'public';
        $table = $tableName;
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $table = $parts[1];
        }

        $columns = (new CatalogQuery())->columns($pdo, $schema, $table);

        if ($columns === []) {
            return null;
        }

        $primaryKeys = (new CatalogQuery())->primaryKeys($pdo, $schema, $table);

        $columnDefs = [];
        foreach ($columns as $col) {
            $def = '"' . $col['column_name'] . '" ' . (new CatalogColumn())->mapDataType($col);
            if ($col['is_nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }
            if ($col['column_default'] !== null) {
                $def .= ' DEFAULT ' . $col['column_default'];
            }
            $columnDefs[] = $def;
        }

        if ($primaryKeys !== []) {
            $columnDefs[] = 'PRIMARY KEY (' . implode(', ', array_map(static fn (string $pk): string => '"' . $pk . '"', $primaryKeys)) . ')';
        }

        return "CREATE TABLE \"{$table}\" (" . implode(', ', $columnDefs) . ')';
    }
}
