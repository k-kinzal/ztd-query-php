<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;

/**
 * Reads the table name, columns and primary key from a CREATE TABLE statement.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Reads the declared table identifier.
     * @throws SchemaParseException
     */
    public function extractTableName(CreateStatement $stmt, string $sql): string
    {
        if ($stmt->name === null) {
            throw SchemaParseException::invalidSql($sql, 'Table name not found');
        }

        $name = $stmt->name->table ?? '';
        return str_replace('`', '', $name);
    }

    /**
     * @return array<string, ColumnDefinition>
     * @throws SchemaParseException
     */
    public function extractColumns(CreateStatement $stmt, string $tableName): array
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
            $column = (new ColumnParser())->parseColumnDefinition($field, $columnName, $primaryKeyColumns);

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
     * @return list<string>
     */
    public function extractPrimaryKeys(CreateStatement $stmt): array
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
}
