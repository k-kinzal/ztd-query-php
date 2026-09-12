<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use ZtdQuery\Schema\TableDefinition;

/**
 * Create Table Renderer.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class CreateTableRenderer
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private string $tableName)
    {
    }
    /**
     * Build a CREATE TABLE SQL from a TableDefinition.
     */
    public function buildCreateTableSql(TableDefinition $definition): string
    {
        $columnDefs = [];
        foreach ($definition->columns as $column) {
            $type = isset($definition->typedColumns[$column])
                ? $definition->typedColumns[$column]->nativeType
                : ($definition->columnTypes[$column] ?? 'TEXT');
            $def = "`{$column}` {$type}";

            if (in_array($column, $definition->notNullColumns, true)) {
                $def .= ' NOT NULL';
            }

            if (in_array($column, $definition->primaryKeys, true) && count($definition->primaryKeys) === 1) {
                $def .= ' PRIMARY KEY';
            }

            $columnDefs[] = $def;
        }

        if (count($definition->primaryKeys) > 1) {
            $pkCols = array_map(fn (string $c) => "`{$c}`", $definition->primaryKeys);
            $columnDefs[] = 'PRIMARY KEY (' . implode(', ', $pkCols) . ')';
        }

        foreach ($definition->uniqueConstraints as $keyName => $columns) {
            $ukCols = array_map(fn (string $c) => "`{$c}`", $columns);
            $columnDefs[] = "UNIQUE KEY `{$keyName}` (" . implode(', ', $ukCols) . ')';
        }

        foreach ($definition->foreignKeys as $keyName => $foreignKey) {
            $columns = array_map(fn (string $column) => "`{$column}`", $foreignKey->columns);
            $referencedColumns = array_map(
                fn (string $column) => "`{$column}`",
                $foreignKey->referencedColumns,
            );
            $columnDefs[] = "CONSTRAINT `{$keyName}` FOREIGN KEY (" . implode(', ', $columns)
                . ") REFERENCES `{$foreignKey->referencedTable}` (" . implode(', ', $referencedColumns) . ')'
                . " ON DELETE {$foreignKey->onDelete->value} ON UPDATE {$foreignKey->onUpdate->value}";
        }

        return "CREATE TABLE `{$this->tableName}` (" . implode(', ', $columnDefs) . ')';
    }
}
