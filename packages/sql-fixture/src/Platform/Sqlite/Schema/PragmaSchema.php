<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use PDO;
use RuntimeException;
use SqlFixture\Schema\TableSchema;

/**
 * Reconstructs a schema from SQLite PRAGMA column metadata.
 *
 * @visibility root
 */
final class PragmaSchema
{
    /**
     * Build schema directly from PRAGMA table_info.
     * @throws RuntimeException
     */
    public function fetchSchemaViaPragma(PDO $pdo, string $tableName): TableSchema
    {
        $safeTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $stmt = $pdo->query("PRAGMA table_info({$safeTableName})");
        if ($stmt === false) {
            throw new RuntimeException("Failed to get schema for table: {$tableName}");
        }

        /**
         * @var array<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}> $rows
         */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        $columns = [];
        $primaryKeys = [];

        foreach ($rows as $row) {
            $column = (new PragmaColumn())->parse($row);
            $columns[$column->name] = $column;
            if ($row['pk'] > 0) {
                $primaryKeys[] = $column->name;
            }
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

    /**
     * Parses default value.
     */
    public function parseDefaultValue(?string $value): int|float|string|null
    {
        if ($value === null) {
            return null;
        }

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (preg_match("/^['\"](.*)['\"]\s*$/s", $value, $matches) === 1) {
            return $matches[1];
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }
}
