<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Simple regex-based parser for SQLite CREATE TABLE statements.
 *
 * SQLite has a simpler type system based on "type affinity" rather than
 * strict types like MySQL. This parser handles the basic SQLite column
 * definitions and extracts type affinity information.
 */
final class SqliteSchemaParser implements SchemaParserInterface
{
    public function parse(string $createTableSql): TableSchema
    {
        $sql = $this->normalizeSql($createTableSql);

        $tableName = $this->extractTableName($sql);
        if ($tableName === null) {
            throw SchemaParseException::invalidSql($createTableSql, 'Could not extract table name');
        }

        $columnsBlock = $this->extractColumnsBlock($sql);
        if ($columnsBlock === null) {
            throw SchemaParseException::noColumns($tableName);
        }

        $primaryKeys = $this->extractTablePrimaryKeys($columnsBlock);
        $columns = $this->parseColumns($columnsBlock, $tableName, $primaryKeys);

        if ($columns === []) {
            throw SchemaParseException::noColumns($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

    private function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = (string) preg_replace('/\/\*.*?\*\//s', '', (string) $sql);

        $result = preg_replace('/\s+/', ' ', trim($sql));

        return $result !== null ? $result : '';
    }

    private function extractTableName(string $sql): ?string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"?(\w+)"?\.)?"?(\w+)"?\s*\(/i', $sql, $matches) === 1) {
            return $matches[2];
        }
        return null;
    }

    private function extractColumnsBlock(string $sql): ?string
    {
        $start = strpos($sql, '(');
        $end = strrpos($sql, ')');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($sql, $start + 1, $end - $start - 1);
    }

    /**
     * @param list<string> $tablePrimaryKeys
     * @return array<string, ColumnDefinition>
     */
    private function parseColumns(string $columnsBlock, string $tableName, array $tablePrimaryKeys): array
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

            $column = $this->parseColumnDefinition($definition, $tablePrimaryKeys);
            if ($column !== null) {
                $columns[$column->name] = $column;
            }
        }

        return $columns;
    }

    /**
     * Split column definitions respecting parentheses nesting.
     *
     * @return list<string>
     */
    private function splitColumnDefinitions(string $columnsBlock): array
    {
        $definitions = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($columnsBlock); $i++) {
            $char = $columnsBlock[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $definitions[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $definitions[] = trim($current);
        }

        return $definitions;
    }

    private function isTableConstraint(string $definition): bool
    {
        $definition = strtoupper(trim($definition));

        return preg_match('/^(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|CONSTRAINT)\b/i', $definition) === 1;
    }

    /**
     * @param list<string> $tablePrimaryKeys
     */
    private function parseColumnDefinition(string $definition, array $tablePrimaryKeys): ?ColumnDefinition
    {
        if (preg_match('/^["`]?(\w+)["`]?\s*(.*)/is', $definition, $matches) !== 1) {
            return null;
        }

        $columnName = $matches[1];
        $rest = trim($matches[2]);

        $type = $this->extractType($rest);
        $length = null;
        $precision = null;
        $scale = null;

        if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $rest, $typeMatches) === 1) {
            $type = strtoupper($typeMatches[1]);
            if (isset($typeMatches[3])) {
                $precision = (int) $typeMatches[2];
                $scale = (int) $typeMatches[3];
            } else {
                $length = (int) $typeMatches[2];
            }
        }

        $upperRest = strtoupper($rest);
        $nullable = !str_contains($upperRest, 'NOT NULL');
        $autoIncrement = str_contains($upperRest, 'AUTOINCREMENT');

        $isPrimaryKey = str_contains($upperRest, 'PRIMARY KEY') || in_array($columnName, $tablePrimaryKeys, true);
        if ($isPrimaryKey) {
            $nullable = false;
        }

        $default = $this->extractDefault($rest);

        $generated = preg_match('/\bAS\s*\(/i', $rest) === 1;

        if ($type === 'INTEGER' && $isPrimaryKey && $autoIncrement) {
            $autoIncrement = true;
        } elseif ($type === 'INTEGER' && $isPrimaryKey) {
            $autoIncrement = false;
        }

        return new ColumnDefinition(
            name: $columnName,
            type: $type,
            length: $length,
            precision: $precision,
            scale: $scale,
            nullable: $nullable,
            unsigned: false, // SQLite doesn't have unsigned
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $generated,
            enumValues: null, // SQLite doesn't have ENUM
        );
    }

    private function extractType(string $rest): string
    {
        if ($rest === '') {
            return 'BLOB';
        }

        if (preg_match('/^(\w+)/i', $rest, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'BLOB';
    }

    private function extractDefault(string $rest): mixed
    {
        if (preg_match('/\bDEFAULT\s+(.+?)(?:\s+(?:NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|COLLATE|GENERATED|AS\s*\()|$)/is', $rest, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        $value = preg_replace('/\s+(NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|COLLATE).*$/i', '', $value);
        $value = trim((string) $value);

        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            return $value;
        }

        if (preg_match("/^['\"](.*)['\"]\s*$/s", $value, $stringMatches) === 1) {
            return $stringMatches[1];
        }

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (strtoupper($value) === 'TRUE' || $value === '1') {
            return true;
        }
        if (strtoupper($value) === 'FALSE' || $value === '0') {
            return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        if (preg_match('/^CURRENT_(?:TIMESTAMP|DATE|TIME)$/i', $value) === 1) {
            return $value;
        }

        return $value;
    }

    /**
     * Extract PRIMARY KEY column names from table-level PRIMARY KEY constraint.
     *
     * @return list<string>
     */
    private function extractTablePrimaryKeys(string $columnsBlock): array
    {
        $primaryKeys = [];

        if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $columnsBlock, $matches) === 1) {
            $columns = explode(',', $matches[1]);
            foreach ($columns as $col) {
                $col = trim($col);
                $col = trim($col, '"`');
                if ($col !== '') {
                    $primaryKeys[] = $col;
                }
            }
        }

        return $primaryKeys;
    }
}
