<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Schema\TableDefinition;

/**
 * Writes the INSERT that a LOAD DATA amounts to.
 *
 * Once the file has been read, LOAD DATA is an INSERT of the rows it held, and
 * writing it as one means the shadow simulates it through the same path as
 * every other write. Which of INSERT, INSERT IGNORE and REPLACE it becomes is
 * what the statement said to do about a row that is already there.
 */
final class MySqlLoadDataInsert
{
    /**
     * @param MySqlIdentifierQuoter $quoter Writes a name as MySQL would write it
     */
    public function __construct(private readonly MySqlIdentifierQuoter $quoter = new MySqlIdentifierQuoter())
    {
    }

    /**
     * Answers the INSERT the loaded rows amount to.
     *
     * A file that held no rows still has to say which columns it would have
     * written, so it becomes a SELECT that answers nothing -- which carries the
     * column list without writing anything.
     *
     * @param LoadStatement $statement Statement being simulated
     * @param string $tableName Table being loaded into
     * @param TableDefinition $definition What that table holds
     * @param list<string> $targets Where each field goes, in order
     * @param array<string, string> $setOperations Column => the expression assigned to it
     * @param list<array<string, string>> $rows The rows the file held
     *
     * @return string The INSERT, REPLACE or INSERT IGNORE it amounts to
     *
     * @throws UnsupportedSqlException When the statement would write no column at all
     */
    public function sqlFor(LoadStatement $statement, string $tableName, TableDefinition $definition, array $targets, array $setOperations, array $rows): string
    {
        /** @var array<string, null> $columns */
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
}
