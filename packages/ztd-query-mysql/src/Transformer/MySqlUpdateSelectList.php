<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * What the SELECT answering an UPDATE carries back.
 *
 * A row read back has to say what it would become and which row it was, so
 * every column the statement assigns is carried as what it assigns, every
 * other column as it stands, and the key a second time under a name of its
 * own. Where the statement writes to several tables at once, each table's
 * columns are carried under names of their own, because one result set has
 * to answer for all of them.
 */
final class MySqlUpdateSelectList
{
    /**
     * Writes the select list that carries one table's changed rows back.
     *
     * Each assignment is carried under the column it assigns, so that what a
     * row would become can be read off the result; every other column of the
     * table is carried as it stands, and the key is carried a second time
     * under a name of its own so the row it was can still be found.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     * @param array<int, string> $columns Columns the table holds
     * @param array<int, string> $primaryKeys Columns that identify one of its rows
     * @param list<string> $assignmentValues What each assignment writes, as the statement wrote it
     * @param string $qualifier The name the statement gave the table
     *
     * @return list<string> The select list, one entry per column carried
     */
    public function selectColumns(
        UpdateStatement $stmt,
        array $columns,
        array $primaryKeys,
        array $assignmentValues,
        string $qualifier,
    ): array {
        $selectCols = [];
        $covered = [];
        foreach ($stmt->set ?? [] as $index => $assignment) {
            $column = MySqlUpdateSelectList::assignedColumn($assignment->column);
            $selectCols[] = ($assignmentValues[$index] ?? $assignment->value) . " AS `{$column}`";
            $covered[$column] = true;
        }
        foreach ($columns as $column) {
            if (!isset($covered[$column])) {
                $selectCols[] = "`{$qualifier}`.`{$column}`";
            }
        }
        $identity = new MutationRowIdentity();
        foreach ($primaryKeys as $primaryKey) {
            $selectCols[] = "`{$qualifier}`.`{$primaryKey}` AS `" . $identity->column($primaryKey) . '`';
        }

        return $selectCols;
    }

    /**
     * Answers the column an assignment writes to, however it was qualified.
     *
     * @param string $written The column, as the statement wrote it
     *
     * @return string The column
     */
    public static function assignedColumn(string $written): string
    {
        $column = trim($written, '`"\'');
        if (!str_contains($column, '.')) {
            return $column;
        }
        $parts = explode('.', $column);

        return trim(end($parts), '`"\'');
    }

    /**
     * Writes the select list that carries every changed row back, and the key it had.
     *
     * A column the statement does not assign carries what it already held, so
     * the row read back is the whole row as it would become. Its key is
     * carried separately, because assigning to a key column changes it, and
     * the row still has to be found by the key it had.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     * @param array<string, array{alias: string}> $targetTables Table name => the name the statement gave it
     * @param list<MultiTableMutationTarget> $targets The tables being written to
     * @param list<string> $assignmentValues What each assignment assigns, in the order written
     *
     * @return list<string> The select list, one entry per column carried back
     */
    public function multiTableSelectColumns(
        UpdateStatement $stmt,
        array $targetTables,
        array $targets,
        array $assignmentValues,
    ): array {
        $assignments = $this->assignmentsByTable($stmt, $targetTables, $assignmentValues);
        $codec = new MultiTableMutationRow();
        $quoter = new MySqlIdentifierQuoter();
        $selectColumns = [];
        foreach ($targets as $targetIndex => $target) {
            $tableInfo = $targetTables[$target->tableName()] ?? null;
            if ($tableInfo === null) {
                continue;
            }
            $alias = $quoter->quote($tableInfo['alias']);
            foreach ($target->columns() as $columnIndex => $column) {
                $value = $assignments[$target->tableName()][$column] ?? $alias . '.' . $quoter->quote($column);
                $metadata = $quoter->quote($codec->valueColumn($targetIndex, $columnIndex));
                $selectColumns[] = "$value AS $metadata";
            }
            foreach ($target->primaryKeys() as $primaryKeyIndex => $primaryKey) {
                $metadata = $quoter->quote($codec->identityColumn($targetIndex, $primaryKeyIndex));
                $selectColumns[] = $alias . '.' . $quoter->quote($primaryKey) . " AS $metadata";
            }
        }

        return $selectColumns;
    }

    /**
     * Answers what each assignment assigns, under the table it writes to.
     *
     * An assignment to a bare column name writes to the first table the
     * statement names, which is what MySQL does with one.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     * @param array<string, array{alias: string}> $targetTables Table name => the name the statement gave it
     * @param list<string> $assignmentValues What each assignment assigns, in the order written
     *
     * @return array<string, array<string, string>> Table => column => what is assigned to it
     */
    public function assignmentsByTable(UpdateStatement $stmt, array $targetTables, array $assignmentValues): array
    {
        $assignments = [];
        $primaryTable = array_key_first($targetTables);
        $qualifiedTables = [];
        foreach ($targetTables as $tableName => $tableInfo) {
            $qualifiedTables[$tableName] = $tableName;
            $qualifiedTables[$tableInfo['alias']] = $tableName;
        }
        foreach ($stmt->set ?? [] as $index => $setOperation) {
            $parts = array_map(MySqlUpdateSelectList::unquoteIdentifier(...), explode('.', $setOperation->column));
            $column = array_pop($parts);
            if ($column === '') {
                continue;
            }
            $tableName = $primaryTable;
            $qualifier = array_pop($parts);
            if ($qualifier !== null) {
                $tableName = $qualifiedTables[$qualifier] ?? $primaryTable;
            }
            if ($tableName !== null) {
                $assignments[$tableName][$column] = $assignmentValues[$index] ?? $setOperation->value;
            }
        }

        return $assignments;
    }

    /**
     * Answers the name a written identifier stands for.
     *
     * @param string $identifier The name, as it was written
     *
     * @return string The name, with the quoting taken off
     */
    public static function unquoteIdentifier(string $identifier): string
    {
        return SqlTokenStream::tokenize($identifier, MySqlLexerProfile::create())->identifierAt()['name'] ?? $identifier;
    }
}
