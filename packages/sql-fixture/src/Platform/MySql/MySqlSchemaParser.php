<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

final class MySqlSchemaParser implements SchemaParserInterface
{
    public function parse(string $createTableSql): TableSchema
    {
        $parser = new Parser($createTableSql);

        if ($parser->statements === []) {
            throw SchemaParseException::invalidSql($createTableSql, 'No statements found');
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            throw SchemaParseException::notCreateTable($createTableSql);
        }

        $tableName = $this->extractTableName($stmt, $createTableSql);
        $columns = $this->extractColumns($stmt, $tableName);
        $primaryKeys = $this->extractPrimaryKeys($stmt);

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

    private function extractTableName(CreateStatement $stmt, string $sql): string
    {
        if ($stmt->name === null) {
            throw SchemaParseException::invalidSql($sql, 'Table name not found');
        }

        $name = $stmt->name->table ?? '';
        return str_replace('`', '', $name);
    }

    /**
     * @return array<string, ColumnDefinition>
     */
    private function extractColumns(CreateStatement $stmt, string $tableName): array
    {
        if (!is_iterable($stmt->fields)) {
            throw SchemaParseException::noColumns($tableName);
        }

        $columns = [];
        $primaryKeyColumns = $this->extractPrimaryKeys($stmt);

        foreach ($stmt->fields as $field) {
            $name = $field->name;
            if (!is_string($name) || $name === '') {
                continue;
            }

            if (!$field->type instanceof DataType) {
                continue;
            }

            $columnName = str_replace('`', '', $name);
            $column = $this->parseColumnDefinition($field, $columnName, $primaryKeyColumns);

            if ($column !== null) {
                $columns[$columnName] = $column;
            }
        }

        if ($columns === []) {
            throw SchemaParseException::noColumns($tableName);
        }

        return $columns;
    }

    /**
     * @param list<string> $primaryKeyColumns
     */
    private function parseColumnDefinition(
        CreateDefinition $field,
        string $columnName,
        array $primaryKeyColumns,
    ): ?ColumnDefinition {
        $type = $field->type;
        if (!$type instanceof DataType || $type->name === null) {
            return null;
        }

        $typeName = strtoupper($type->name);
        $options = $field->options;

        $nullable = true;
        if ($options instanceof OptionsArray && $options->has('NOT NULL') !== false) {
            $nullable = false;
        }
        if (in_array($columnName, $primaryKeyColumns, true)) {
            $nullable = false;
        }
        if ($options instanceof OptionsArray && $options->has('PRIMARY KEY') !== false) {
            $nullable = false;
        }

        $unsigned = false;
        if ($options instanceof OptionsArray && $options->has('UNSIGNED') !== false) {
            $unsigned = true;
        }
        if ($type->options->has('UNSIGNED') !== false) {
            $unsigned = true;
        }

        $autoIncrement = false;
        if ($options instanceof OptionsArray && $options->has('AUTO_INCREMENT') !== false) {
            $autoIncrement = true;
        }

        $generated = false;
        if ($options instanceof OptionsArray && ($options->has('GENERATED') !== false || $options->has('AS') !== false)) {
            $generated = true;
        }

        $length = null;
        $precision = null;
        $scale = null;

        $parameters = $type->parameters;
        if ($parameters !== []) {
            if ($this->isDecimalType($typeName)) {
                $precision = isset($parameters[0]) ? (int) $parameters[0] : 10;
                $scale = isset($parameters[1]) ? (int) $parameters[1] : 0;
            } elseif ($this->isBitType($typeName)) {
                $length = isset($parameters[0]) ? (int) $parameters[0] : 1;
            } else {
                $length = isset($parameters[0]) ? (int) $parameters[0] : null;
            }
        }

        $default = $this->extractDefault($options);

        $enumValues = null;
        if ($typeName === 'ENUM' || $typeName === 'SET') {
            $enumValues = $this->extractEnumValues($parameters);
        }

        return new ColumnDefinition(
            name: $columnName,
            type: $typeName,
            length: $length,
            precision: $precision,
            scale: $scale,
            nullable: $nullable,
            unsigned: $unsigned,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $generated,
            enumValues: $enumValues,
        );
    }

    /**
     * @return list<string>
     */
    private function extractPrimaryKeys(CreateStatement $stmt): array
    {
        if (!is_iterable($stmt->fields)) {
            return [];
        }

        $primaryKeys = [];
        foreach ($stmt->fields as $field) {
            if ($field->options instanceof OptionsArray && $field->options->has('PRIMARY KEY') !== false) {
                $name = $field->name;
                if (is_string($name) && $name !== '') {
                    $primaryKeys[] = str_replace('`', '', $name);
                }
            }

            if ($field->key !== null && $field->key->type === 'PRIMARY KEY') {
                foreach ($field->key->columns as $col) {
                    $colName = $col['name'] ?? null;
                    if (is_string($colName) && $colName !== '') {
                        $primaryKeys[] = str_replace('`', '', $colName);
                    }
                }
            }
        }

        return $primaryKeys;
    }

    private function isDecimalType(string $type): bool
    {
        return in_array($type, ['DECIMAL', 'NUMERIC', 'DEC', 'FIXED'], true);
    }

    private function isBitType(string $type): bool
    {
        return $type === 'BIT';
    }

    private function extractDefault(?OptionsArray $options): mixed
    {
        if ($options === null) {
            return null;
        }

        if ($options->has('DEFAULT') === false) {
            return null;
        }

        $optionsArray = $options->options;

        foreach ($optionsArray as $option) {
            if (!is_array($option)) {
                continue;
            }

            $name = $option['name'] ?? null;
            if ($name === 'DEFAULT') {
                $value = $option['value'] ?? null;
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    if (preg_match('/^[\'"](.*)[\'"]\s*$/s', $value, $matches) === 1) {
                        return $matches[1];
                    }
                    if (strtoupper($value) === 'NULL') {
                        return null;
                    }
                    if (strtoupper($value) === 'TRUE') {
                        return true;
                    }
                    if (strtoupper($value) === 'FALSE') {
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
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $parameters
     * @return list<string>
     */
    private function extractEnumValues(array $parameters): array
    {
        $values = [];
        foreach ($parameters as $param) {
            if (is_string($param)) {
                $value = trim($param, '\'"');
                $values[] = $value;
            }
        }
        return $values;
    }
}
