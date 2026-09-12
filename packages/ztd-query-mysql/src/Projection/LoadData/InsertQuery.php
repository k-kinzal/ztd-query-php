<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Schema\TableDefinition;

/**
 * Insert Query.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class InsertQuery
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly MySqlIdentifierQuoter $quoter)
    {
    }
    /**
     * @param list<string> $targets
     * @param array<string, string> $setOperations
     * @param list<array<string, string>> $rows
     * @throws UnsupportedSqlException
     */
    public function buildInsertSql(
        LoadStatement $statement,
        string $tableName,
        TableDefinition $definition,
        array $targets,
        array $setOperations,
        array $rows,
    ): string {
        $orderedColumns = $this->orderedColumns($targets, $setOperations, $definition);
        if ($orderedColumns === []) {
            throw new UnsupportedSqlException($statement->build(), 'LOAD DATA has no target columns');
        }

        $mode = $statement->replace_ignore;
        if ($mode === 'REPLACE') {
            $prefix = 'REPLACE INTO ';
        } else {
            $local = $statement->options !== null && $statement->options->has('LOCAL') !== false;
            $prefix = ($mode === 'IGNORE' || $local) ? 'INSERT IGNORE INTO ' : 'INSERT INTO ';
        }
        $columnSql = implode(', ', array_map($this->quoter->quote(...), $orderedColumns));
        $targetSql = $this->quoter->quote($tableName) . ' (' . $columnSql . ')';
        if ($rows === []) {
            $selects = [];
            foreach ($orderedColumns as $column) {
                $selects[] = 'NULL AS ' . $this->quoter->quote($column);
            }

            return $prefix . $targetSql . ' SELECT ' . implode(', ', $selects) . ' WHERE FALSE';
        }

        $valueRows = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($orderedColumns as $column) {
                $values[] = $row[$column] ?? 'DEFAULT';
            }
            $valueRows[] = '(' . implode(', ', $values) . ')';
        }

        return $prefix . $targetSql . ' VALUES ' . implode(', ', $valueRows);
    }
    /**
     * @param list<string> $targets
     * @param array<string, string> $setOperations
     * @return list<string>
     */
    public function orderedColumns(array $targets, array $setOperations, TableDefinition $definition): array
    {
        /**
         * @var array<string, null> $columns
         */
        $columns = [];
        foreach ($targets as $target) {
            if ($target[0] !== '@') {
                $columns[$target] = null;
            }
        }
        foreach (array_keys($setOperations) as $column) {
            $columns[$column] = null;
        }
        $orderedColumns = [];
        foreach ($definition->columns as $column) {
            if (array_key_exists($column, $columns)) {
                $orderedColumns[] = $column;
            }
        }
        return $orderedColumns;
    }

}
