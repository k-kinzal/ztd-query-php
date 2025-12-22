<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;

/**
 * MySQL implementation of SchemaParser using phpMyAdmin SQL parser.
 */
final class MySqlSchemaParser implements SchemaParser
{
    private MySqlParser $parser;

    public function __construct(MySqlParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritDoc}
     */
    public function parse(string $createTableSql): ?TableDefinition
    {
        $statements = $this->parser->parse($createTableSql);
        if ($statements === []) {
            return null;
        }

        $stmt = $statements[0];
        if (!$stmt instanceof CreateStatement) {
            return null;
        }

        if (!is_iterable($stmt->fields)) {
            return null;
        }

        $columns = [];
        $columnTypes = [];
        /** @var array<string, ColumnType> $typedColumns */
        $typedColumns = [];
        $primaryKeys = [];
        $notNullColumns = [];
        $uniqueConstraints = [];
        $uniqueIndex = 0;

        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;

            if (is_string($name) && $name !== '') {
                $columnName = str_replace('`', '', $name);
                $columns[] = $columnName;

                if ($field->type !== null && $field->type->name !== null) {
                    $typeName = strtoupper($field->type->name);
                    if ($field->type->parameters !== [] && $field->type->parameters !== null) {
                        $typeName .= '(' . implode(',', $field->type->parameters) . ')';
                    }
                    $columnTypes[$columnName] = $typeName;
                    $typedColumns[$columnName] = new ColumnType(
                        $this->mapToColumnTypeFamily($typeName),
                        $typeName,
                    );
                }

                if ($field->options !== null && self::optionSet($field->options, 'NOT NULL')) {
                    $notNullColumns[] = $columnName;
                }

                if ($field->options !== null && self::optionSet($field->options, 'PRIMARY KEY')) {
                    $primaryKeys[] = $columnName;
                    if (!in_array($columnName, $notNullColumns, true)) {
                        $notNullColumns[] = $columnName;
                    }
                }

                if ($field->options !== null && self::optionSet($field->options, 'UNIQUE')) {
                    $keyName = $columnName . '_UNIQUE';
                    $uniqueConstraints[$keyName] = [$columnName];
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

            if ($field->key !== null && ($field->key->type === 'UNIQUE' || $field->key->type === 'UNIQUE KEY')) {
                $constraintColumns = [];
                foreach ($field->key->columns as $col) {
                    $colName = $col['name'] ?? null;
                    if (is_string($colName) && $colName !== '') {
                        $constraintColumns[] = str_replace('`', '', $colName);
                    }
                }
                if ($constraintColumns !== []) {
                    $keyName = ($field->key->name !== null && $field->key->name !== '') ? $field->key->name : ('unique_' . $uniqueIndex++);
                    $uniqueConstraints[$keyName] = $constraintColumns;
                }
            }
        }

        return new TableDefinition(
            $columns,
            $columnTypes,
            $primaryKeys,
            $notNullColumns,
            $uniqueConstraints,
            $typedColumns,
        );
    }

    /**
     * Check whether the given OptionsArray has a specific option set.
     *
     * @param \PhpMyAdmin\SqlParser\Components\OptionsArray $options
     */
    private static function optionSet(\PhpMyAdmin\SqlParser\Components\OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }

    /**
     * Map a MySQL type string to a ColumnTypeFamily.
     */
    private function mapToColumnTypeFamily(string $mysqlType): ColumnTypeFamily
    {
        $replaced = preg_replace('/\(.*\)/', '', strtoupper($mysqlType));
        $baseType = is_string($replaced) ? $replaced : strtoupper($mysqlType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' => ColumnTypeFamily::INTEGER,
            'DECIMAL', 'NUMERIC' => ColumnTypeFamily::DECIMAL,
            'FLOAT' => ColumnTypeFamily::FLOAT,
            'DOUBLE', 'REAL' => ColumnTypeFamily::DOUBLE,
            'BOOL', 'BOOLEAN' => ColumnTypeFamily::BOOLEAN,
            'DATE' => ColumnTypeFamily::DATE,
            'TIME' => ColumnTypeFamily::TIME,
            'DATETIME' => ColumnTypeFamily::DATETIME,
            'TIMESTAMP' => ColumnTypeFamily::TIMESTAMP,
            'JSON' => ColumnTypeFamily::JSON,
            'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB' => ColumnTypeFamily::BINARY,
            'CHAR', 'VARCHAR', 'ENUM', 'SET' => ColumnTypeFamily::STRING,
            'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT' => ColumnTypeFamily::TEXT,
            'YEAR' => ColumnTypeFamily::INTEGER,
            'BIT' => ColumnTypeFamily::INTEGER,
            default => ColumnTypeFamily::UNKNOWN,
        };
    }
}
