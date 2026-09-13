<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads the table name, columns and primary key from a CREATE TABLE statement.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Reads the declared table identifier.
     * @throws \SqlFixture\Schema\Exception\InvalidSqlException
     */
    public function extractTableName(CreateStatement $stmt, string $sql): string
    {
        if ($stmt->name === null) {
            throw new \SqlFixture\Schema\Exception\InvalidSqlException($sql, 'Table name not found');
        }

        $name = $stmt->name->table ?? '';
        return $name;
    }

    /**
     * @return array<string, ColumnDefinition>
     * @throws \SqlFixture\Schema\Exception\MissingColumnDefinitionsException
     */
    public function extractColumns(CreateStatement $stmt, string $tableName): array
    {
        if (!is_iterable($stmt->fields)) {
            throw new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException($tableName);
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

            $columnName = $name;
            $column = (new ColumnParser())->parseColumnDefinition($field, $columnName, $primaryKeyColumns);

            if ($column !== null) {
                $columns[$columnName] = $column;
            }
        }

        if ($columns === []) {
            throw new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException($tableName);
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
                    $primaryKeys[] = $name;
                }
            }

            if ($field->key !== null && $field->key->type === 'PRIMARY KEY') {
                foreach ($field->key->columns as $col) {
                    $colName = $col['name'] ?? null;
                    if (is_string($colName) && $colName !== '') {
                        $primaryKeys[] = $colName;
                    }
                }
            }
        }

        return $primaryKeys;
    }
}
