<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use PDO;
use RuntimeException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from PostgreSQL databases.
 *
 * Uses information_schema to query column definitions, since PostgreSQL
 * does not have a SHOW CREATE TABLE equivalent.
 */
final class PostgreSqlSchemaFetcher implements SchemaFetcherInterface
{
    private PostgreSqlSchemaParser $parser;

    public function __construct(?PostgreSqlSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new PostgreSqlSchemaParser();
    }

    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = $this->reconstructCreateTable($pdo, $tableName);

        if ($createTableSql !== null) {
            return $this->parser->parse($createTableSql);
        }

        return $this->fetchSchemaFromInformationSchema($pdo, $tableName);
    }

    private function reconstructCreateTable(PDO $pdo, string $tableName): ?string
    {
        $schema = 'public';
        $table = $tableName;
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $table = $parts[1];
        }

        $stmt = $pdo->prepare(
            'SELECT column_name, data_type, character_maximum_length, '
            . 'numeric_precision, numeric_scale, is_nullable, column_default, '
            . 'udt_name '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = :schema AND table_name = :table '
            . 'ORDER BY ordinal_position'
        );
        $stmt->execute(['schema' => $schema, 'table' => $table]);

        /** @var list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> $columns */
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($columns === []) {
            return null;
        }

        $pkStmt = $pdo->prepare(
            'SELECT a.attname '
            . 'FROM pg_index i '
            . 'JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) '
            . 'WHERE i.indrelid = :table_oid::regclass AND i.indisprimary'
        );

        try {
            $qualifiedTable = $schema === 'public' ? "\"{$table}\"" : "\"{$schema}\".\"{$table}\"";
            $pkStmt->execute(['table_oid' => $qualifiedTable]);
            /** @var list<array{attname: string}> $pkRows */
            $pkRows = $pkStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $pkRows = [];
        }

        $primaryKeys = array_map(static fn (array $row): string => $row['attname'], $pkRows);

        $columnDefs = [];
        foreach ($columns as $col) {
            $def = '"' . $col['column_name'] . '" ' . $this->mapDataType($col);
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

    /**
     * @param array{data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, udt_name: string} $col
     */
    private function mapDataType(array $col): string
    {
        $type = strtoupper($col['data_type']);

        if ($type === 'CHARACTER VARYING' && $col['character_maximum_length'] !== null) {
            return 'VARCHAR(' . $col['character_maximum_length'] . ')';
        }

        if ($type === 'CHARACTER' && $col['character_maximum_length'] !== null) {
            return 'CHAR(' . $col['character_maximum_length'] . ')';
        }

        if ($type === 'NUMERIC' && $col['numeric_precision'] !== null) {
            $precision = $col['numeric_precision'];
            if ($col['numeric_scale'] !== null && $col['numeric_scale'] !== '0') {
                return "NUMERIC({$precision}, {$col['numeric_scale']})";
            }
            return "NUMERIC({$precision})";
        }

        if ($type === 'ARRAY') {
            return strtoupper($col['udt_name']);
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($col['udt_name']);
        }

        return $type;
    }

    private function fetchSchemaFromInformationSchema(PDO $pdo, string $tableName): TableSchema
    {
        $schema = 'public';
        $table = $tableName;
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $table = $parts[1];
        }

        $stmt = $pdo->prepare(
            'SELECT column_name, data_type, character_maximum_length, '
            . 'numeric_precision, numeric_scale, is_nullable, column_default, '
            . 'udt_name '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = :schema AND table_name = :table '
            . 'ORDER BY ordinal_position'
        );
        $stmt->execute(['schema' => $schema, 'table' => $table]);

        /** @var list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        $columns = [];
        foreach ($rows as $row) {
            $columnName = $row['column_name'];
            $type = $this->resolveType($row);
            $length = $row['character_maximum_length'] !== null ? (int) $row['character_maximum_length'] : null;
            $precision = $row['numeric_precision'] !== null ? (int) $row['numeric_precision'] : null;
            $scale = $row['numeric_scale'] !== null ? (int) $row['numeric_scale'] : null;
            $nullable = $row['is_nullable'] === 'YES';
            $default = $this->parseDefault($row['column_default']);
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

    /**
     * @param array{data_type: string, udt_name: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string} $row
     */
    private function resolveType(array $row): string
    {
        $type = strtoupper($row['data_type']);

        if ($type === 'ARRAY') {
            $elementType = strtoupper(ltrim($row['udt_name'], '_'));
            return $elementType . '_ARRAY';
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($row['udt_name']);
        }

        return $type;
    }

    private function parseDefault(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (strtoupper($value) === 'NULL' || strtoupper($value) === 'NULL::') {
            return null;
        }

        if (str_contains($value, 'nextval(')) {
            return null;
        }

        if (preg_match("/^'(.*)'::.*$/s", $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match("/^'(.*)'$/s", $value, $matches) === 1) {
            return $matches[1];
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
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
