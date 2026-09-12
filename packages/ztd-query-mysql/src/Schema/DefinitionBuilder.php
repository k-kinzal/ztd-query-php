<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TablePartitioning;

/**
 * Accumulates column and key declarations into a shadow table definition.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class DefinitionBuilder
{
    /**
     * @var list<string>
     */
    private array $columns = [];

    /**
     * @var array<string, string>
     */
    private array $columnTypes = [];

    /**
     * @var array<string, ColumnType>
     */
    private array $typedColumns = [];

    /**
     * @var array<string, string>
     */
    private array $columnDefaults = [];

    /**
     * @var array<string, IdentityGenerationStrategy>
     */
    private array $identityStrategies = [];

    /**
     * @var array<string, string>
     */
    private array $generatedExpressions = [];

    /**
     * @var list<string>
     */
    private array $primaryKeys = [];

    /**
     * @var list<string>
     */
    private array $notNullColumns = [];

    /**
     * @var array<string, list<string>>
     */
    private array $uniqueConstraints = [];

    private int $uniqueIndex = 0;

    /**
     * Add a parsed column, including its defaults and identity strategy.
     */
    public function addColumn(CreateDefinition $field): void
    {
        if ($field->type === null) {
            return;
        }

        $columnName = $field->name ?? '';
        if ($columnName === '') {
            return;
        }

        $this->columns[] = $columnName;

        if ($field->type->name !== null) {
            $typeName = strtoupper($field->type->name);
            if ($field->type->parameters !== [] && $field->type->parameters !== null) {
                $typeName .= '(' . implode(',', $field->type->parameters) . ')';
            }
            $this->columnTypes[$columnName] = $typeName;
            $this->typedColumns[$columnName] = (new MySqlColumnTypeMapper())->map($typeName);
        }

        if ($field->options !== null) {
            $this->addColumnOptions($columnName, $field->options);
        }
    }

    /**
     * Add constraints, defaults and generation options for a declared column.
     */
    public function addColumnOptions(string $columnName, \PhpMyAdmin\SqlParser\Components\OptionsArray $options): void
    {
        if (($options->has('NOT NULL') !== false)) {
            $this->notNullColumns[] = $columnName;
        }

        if (($options->has('PRIMARY KEY') !== false)) {
            $this->primaryKeys[] = $columnName;
            if (!in_array($columnName, $this->notNullColumns, true)) {
                $this->notNullColumns[] = $columnName;
            }
        }

        if (($options->has('UNIQUE') !== false)) {
            $keyName = $columnName . '_UNIQUE';
            $this->uniqueConstraints[$keyName] = [$columnName];
        }

        $default = $options->has('DEFAULT');
        if (is_string($default)) {
            $this->columnDefaults[$columnName] = $default;
        }
        if (($options->has('AUTO_INCREMENT') !== false)) {
            $this->identityStrategies[$columnName] = IdentityGenerationStrategy::MaxValue;
        }
        $generatedExpression = $options->has('AS');
        if (is_string($generatedExpression) && $generatedExpression !== '') {
            $this->generatedExpressions[$columnName] = $generatedExpression;
        }
    }

    /**
     * Add table-level primary and unique keys.
     */
    public function addKey(CreateDefinition $field): void
    {
        if ($field->key !== null && $field->key->type === 'PRIMARY KEY') {
            foreach ($field->key->columns as $col) {
                $colName = $col['name'] ?? null;
                if (is_string($colName) && $colName !== '') {
                    $this->primaryKeys[] = str_replace('`', '', $colName);
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
                $keyName = ($field->key->name !== null && $field->key->name !== '') ? $field->key->name : ('unique_' . $this->uniqueIndex++);
                $this->uniqueConstraints[$keyName] = $constraintColumns;
            }
        }
    }

    /**
     * @param array<string, ForeignKeyDefinition> $foreignKeys
     */
    public function build(array $foreignKeys, ?TablePartitioning $partitioning): TableDefinition
    {
        return new TableDefinition(
            $this->columns,
            $this->columnTypes,
            $this->primaryKeys,
            $this->notNullColumns,
            $this->uniqueConstraints,
            $this->typedColumns,
            $this->columnDefaults,
            $this->identityStrategies,
            $this->generatedExpressions,
            $foreignKeys,
            $partitioning,
        );
    }
}
