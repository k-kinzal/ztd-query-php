<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use ZtdQuery\Schema\TableDefinition;

/**
 * Writes a table's definition back out as the CREATE TABLE that would declare it.
 *
 * ALTER TABLE is written against a declaration, not against a definition, and
 * the only thing that can read an ALTER is the parser. So the definition the
 * shadow holds is written back out as SQL, altered as text, and read again —
 * which keeps one reader for declarations instead of two.
 */
final class MySqlTableRedeclaration
{
    /**
     * Answers the CREATE TABLE that would declare this table.
     *
     * A column ZTD never learned a type for is declared TEXT, because MySQL
     * will not take a column with no type at all and TEXT refuses nothing.
     *
     * @param string $tableName Table to declare
     * @param TableDefinition $definition What that table holds
     *
     * @return string The declaration
     */
    public function sqlFor(string $tableName, TableDefinition $definition): string
    {
        $declarations = [];
        foreach ($definition->columns as $column) {
            $declarations[] = $this->columnSql($column, $definition);
        }

        if (count($definition->primaryKeys) > 1) {
            $declarations[] = 'PRIMARY KEY (' . $this->quotedList($definition->primaryKeys) . ')';
        }

        foreach ($definition->uniqueConstraints as $keyName => $columns) {
            $declarations[] = "UNIQUE KEY `{$keyName}` (" . $this->quotedList($columns) . ')';
        }

        foreach ($definition->foreignKeys as $keyName => $foreignKey) {
            $declarations[] = "CONSTRAINT `{$keyName}` FOREIGN KEY (" . $this->quotedList($foreignKey->columns) . ')'
                . " REFERENCES `{$foreignKey->referencedTable}` (" . $this->quotedList($foreignKey->referencedColumns) . ')'
                . " ON DELETE {$foreignKey->onDelete->value} ON UPDATE {$foreignKey->onUpdate->value}";
        }

        return "CREATE TABLE `{$tableName}` (" . implode(', ', $declarations) . ')';
    }

    /**
     * Answers how one column would be declared.
     *
     * A key of one column is written on the column itself, which is what MySQL
     * writes back when asked, and what keeps a one-column key readable.
     *
     * @param string $column Column to declare
     * @param TableDefinition $definition What the table holds
     *
     * @return string The column's part of the declaration
     */
    public function columnSql(string $column, TableDefinition $definition): string
    {
        $type = isset($definition->typedColumns[$column])
            ? $definition->typedColumns[$column]->nativeType
            : ($definition->columnTypes[$column] ?? 'TEXT');
        $sql = "`{$column}` {$type}";

        if (in_array($column, $definition->notNullColumns, true)) {
            $sql .= ' NOT NULL';
        }
        if (in_array($column, $definition->primaryKeys, true) && count($definition->primaryKeys) === 1) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    /**
     * Answers a run of names as MySQL would write them.
     *
     * @param list<string> $names Names to write
     *
     * @return string The names, quoted and comma-separated
     */
    public function quotedList(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => "`{$name}`", $names));
    }
}
