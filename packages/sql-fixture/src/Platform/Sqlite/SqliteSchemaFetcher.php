<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use PDO;
use RuntimeException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from SQLite databases.
 *
 * Uses PRAGMA table_info to build the schema directly, avoiding the need
 * to parse CREATE TABLE statements for simple cases. Falls back to
 * sqlite_schema for full CREATE TABLE parsing when needed.
 */
final class SqliteSchemaFetcher implements SchemaFetcherInterface
{
    private SqliteSchemaParser $parser;

    public function __construct(?SqliteSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new SqliteSchemaParser();
    }

    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = $this->fetchCreateTableSql($pdo, $tableName);

        if ($createTableSql !== null) {
            return $this->parser->parse($createTableSql);
        }

        return $this->fetchSchemaViaPragma($pdo, $tableName);
    }

    /**
     * Fetch the CREATE TABLE SQL from sqlite_schema.
     */
    private function fetchCreateTableSql(PDO $pdo, string $tableName): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name'
        );
        $stmt->execute(['type' => 'table', 'name' => $tableName]);

        /** @var array{sql: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || $row['sql'] === '') {
            return null;
        }

        return $row['sql'];
    }

    /**
     * Build schema directly from PRAGMA table_info.
     */
    private function fetchSchemaViaPragma(PDO $pdo, string $tableName): TableSchema
    {
        $safeTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $stmt = $pdo->query("PRAGMA table_info({$safeTableName})");
        if ($stmt === false) {
            throw new RuntimeException("Failed to get schema for table: {$tableName}");
        }

        /** @var array<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        $columns = [];
        $primaryKeys = [];

        foreach ($rows as $row) {
            $columnName = $row['name'];
            $type = strtoupper($row['type'] !== '' ? $row['type'] : 'BLOB');
            $nullable = $row['notnull'] === 0;
            $default = $this->parseDefaultValue($row['dflt_value']);
            $isPrimaryKey = $row['pk'] > 0;

            if ($isPrimaryKey) {
                $primaryKeys[] = $columnName;
                $nullable = false;
            }

            $length = null;
            $precision = null;
            $scale = null;

            if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $type, $typeMatches) === 1) {
                $type = strtoupper($typeMatches[1]);
                if (isset($typeMatches[3])) {
                    $precision = (int) $typeMatches[2];
                    $scale = (int) $typeMatches[3];
                } else {
                    $length = (int) $typeMatches[2];
                }
            }

            $autoIncrement = false;

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
                generated: false, // PRAGMA doesn't expose this
                enumValues: null,
            );
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

    private function parseDefaultValue(?string $value): mixed
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
