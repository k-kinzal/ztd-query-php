<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Update;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Shadow\Mutation\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Target Projection.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class TargetProjection
{
    /**
     * @param array<string, array{alias: string}> $targetTables
     * @param array<string, array{viewSql: string}|array{columns: array<int, string>, primaryKeys?: array<int, string>}> $contexts
     * @return list<MultiTableMutationTarget>
     */
    public function targetsFromContexts(array $targetTables, array $contexts): array
    {
        $targets = [];
        foreach ($targetTables as $tableName => $tableInfo) {
            $context = $contexts[$tableName] ?? null;
            if (!isset($context['columns'])) {
                continue;
            }
            $targets[] = new MultiTableMutationTarget(
                $tableName,
                $context['columns'],
                $context['primaryKeys'] ?? [],
            );
        }

        return $targets;
    }

    /**
     * @param array<string, array{alias: string}> $targetTables
     * @param list<MultiTableMutationTarget> $targets
     * @param list<string> $assignmentValues
     * @return list<string>
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
     * @param array<string, array{alias: string}> $targetTables
     * @param list<string> $assignmentValues
     * @return array<string, array<string, string>>
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
            $parts = array_map(self::unquoteIdentifier(...), explode('.', $setOperation->column));
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
     * Unquote Identifier for the supplied MySQL input.
     */
    public static function unquoteIdentifier(string $identifier): string
    {
        return SqlTokenStream::tokenize($identifier, MySqlLexerProfile::create())->identifierAt()['name'] ?? $identifier;
    }

    /**
     * Build Additional Tables for the supplied MySQL input.
     */
    public function buildAdditionalTables(UpdateStatement $stmt): string
    {
        if ($stmt->tables === null || count($stmt->tables) <= 1) {
            return '';
        }

        $parts = [];
        $tableCount = count($stmt->tables);
        for ($i = 1; $i < $tableCount; $i++) {
            $tableExpr = $stmt->tables[$i];
            $tableName = (($tableExpr->table ?? '') !== '' ? $tableExpr->table : $tableExpr->expr ?? null) ?? '';
            $alias = $tableExpr->alias ?? '';

            $part = "`$tableName`";
            if ($alias !== '' && $alias !== $tableName) {
                $part .= " AS $alias";
            }
            $parts[] = $part;
        }

        return ', ' . implode(', ', $parts);
    }

    /**
     * Build Join Clause for the supplied MySQL input.
     */
    public function buildJoinClause(UpdateStatement $stmt): string
    {
        if ($stmt->join === null || $stmt->join === []) {
            return '';
        }

        $joinParts = [];
        foreach ($stmt->join as $join) {
            $joinType = $join->type !== '' ? $join->type : 'JOIN';
            if (!str_contains(strtoupper($joinType), 'JOIN')) {
                $joinType .= ' JOIN';
            }
            $joinTable = $join->expr !== null ? ((($join->expr->table ?? '') !== '' ? $join->expr->table : $join->expr->expr ?? null) ?? '') : '';
            $joinAlias = $join->expr !== null ? ($join->expr->alias ?? '') : '';

            $joinStr = " $joinType `$joinTable`";
            if ($joinAlias !== '') {
                $joinStr .= " AS $joinAlias";
            }

            if ($join->on !== null && $join->on !== []) {
                $onParts = [];
                foreach ($join->on as $condition) {
                    $onParts[] = $condition->expr !== '' ? $condition->expr : Condition::build([$condition]);
                }
                $joinStr .= ' ON ' . implode(' ', $onParts);
            }

            if ($join->using !== null) {
                $usingValues = $join->using->values;
                if ($usingValues !== []) {
                    $joinStr .= ' USING (' . implode(', ', $usingValues) . ')';
                }
            }

            $joinParts[] = $joinStr;
        }

        return implode('', $joinParts);
    }
    /**
     * Resolve the primary UPDATE target before reading its schema context.
     *
     * @throws \ZtdQuery\Exception\UnsupportedSqlException
     */
    public function targetTable(UpdateStatement $statement, string $sql): string
    {
        if ($statement->tables === [] || !isset($statement->tables[0])) {
            throw new \ZtdQuery\Exception\UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $targetExpr = $statement->tables[0];
        $targetTable = (($targetExpr->table ?? '') !== '' ? $targetExpr->table : $targetExpr->expr ?? null);
        if ($targetTable === null) {
            throw new \ZtdQuery\Exception\UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        return $targetTable;
    }

}
