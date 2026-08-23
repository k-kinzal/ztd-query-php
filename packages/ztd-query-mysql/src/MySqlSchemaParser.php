<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\IdentityGenerationStrategy;
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
        $columnDefaults = [];
        $identityStrategies = [];
        $generatedExpressions = [];
        $primaryKeys = [];
        $notNullColumns = [];
        $uniqueConstraints = [];
        $uniqueIndex = 0;
        $foreignKeys = (new MySqlForeignKeyDefinitionParser())->parseCreateTable($createTableSql);
        $partitioning = is_string($stmt->partitionBy)
            ? (new MySqlPartitioningParser())->parse($stmt)
            : null;

        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;

            if ($field->type !== null) {
                $columnName = $name ?? '';
                if ($columnName === '') {
                    continue;
                }

                $columns[] = $columnName;

                if ($field->type->name !== null) {
                    $typeName = strtoupper($field->type->name);
                    if ($field->type->parameters !== [] && $field->type->parameters !== null) {
                        $typeName .= '(' . implode(',', $field->type->parameters) . ')';
                    }
                    $columnTypes[$columnName] = $typeName;
                    $typedColumns[$columnName] = (new MySqlColumnTypeMapper())->map($typeName);
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

                if ($field->options !== null) {
                    $default = $field->options->has('DEFAULT');
                    if (is_string($default)) {
                        $columnDefaults[$columnName] = $default;
                    }
                }
                if ($field->options !== null && self::optionSet($field->options, 'AUTO_INCREMENT')) {
                    $identityStrategies[$columnName] = IdentityGenerationStrategy::MaxValue;
                }
                if ($field->options !== null) {
                    $generatedExpression = $field->options->has('AS');
                    if (is_string($generatedExpression) && $generatedExpression !== '') {
                        $generatedExpressions[$columnName] = $generatedExpression;
                    }
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
            $columnDefaults,
            $identityStrategies,
            $generatedExpressions,
            $foreignKeys,
            $partitioning,
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

}
